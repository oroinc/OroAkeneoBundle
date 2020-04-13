<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\ImportExport\Strategy\ProductStrategy;

/**
 * Strategy to import products.
 */
class ProductImportStrategy extends ProductStrategy
{
    use ImportStrategyAwareHelperTrait;

    /**
     * @var Product[]
     *
     * Cache existing product request in scope of a single row processing to avoid excess DB queries
     */
    private $existingProducts = [];

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];
        $this->cachedInverseSingleRelations = [];
        $this->cachedExistingEntities = [];
        $this->cachedInverseMultipleRelations = [];

        $this->processedProducts = [];

        $this->databaseHelper->onClear();

        parent::close();
    }

    protected function beforeProcessEntity($entity)
    {
        $existingProduct = $this->findExistingEntity($entity);
        if ($existingProduct instanceof Product) {
            $entity->setStatus($existingProduct->getStatus());
            $entity->setInventoryStatus($existingProduct->getInventoryStatus());
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        if ($entity instanceof Product && $entity->getCategory() instanceof Category) {
            if (!$entity->getCategory()->getId()) {
                $entity->setCategory(null);
            }
        }

        $this->processedProducts = [];

        return parent::afterProcessEntity($entity);
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Product && array_key_exists($entity->getSku(), $this->existingProducts)) {
            return $this->existingProducts[$entity->getSku()];
        }

        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $entity->getChannel()]
            );
        }

        if ($entity instanceof File) {
            return $searchContext[$entity->getOriginalFilename()] ?? null;
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatKey($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        $entity = parent::findExistingEntity($entity, $searchContext);

        if ($entity instanceof Product) {
            $this->existingProducts[$entity->getSku()] = $entity;
        }

        return $entity;
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Product && array_key_exists($entity->getSku(), $this->existingProducts)) {
            return $this->existingProducts[$entity->getSku()];
        }

        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $entity->getChannel()]
            );
        }

        if ($entity instanceof File) {
            return $searchContext[$entity->getOriginalFilename()] ?? null;
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatKey($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        $entity = parent::findExistingEntityByIdentityFields($entity, $searchContext);

        if ($entity instanceof Product) {
            $this->existingProducts[$entity->getSku()] = $entity;
        }

        return $entity;
    }

    /**
     * @param object $entity
     *
     * @see \Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper::importEntity
     * @see \Oro\Bundle\AkeneoBundle\ImportExport\Strategy\ImportStrategyHelper::importEntity
     * @see \Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy::updateRelations
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function updateRelations($entity, array $itemData = null)
    {
        $entityName = $this->doctrineHelper->getEntityClass($entity);
        $fields = $this->fieldHelper->getFields($entityName, true);

        foreach ($fields as $field) {
            if ($this->fieldHelper->isRelation($field)) {
                $fieldName = $field['name'];
                $isFullRelation = $this->fieldHelper->getConfigValue($entityName, $fieldName, 'full', false);
                $isPersistRelation = $this->databaseHelper->isCascadePersist($entityName, $fieldName);

                $searchContext = $this->generateSearchContextForRelationsUpdate(
                    $entity,
                    $entityName,
                    $fieldName,
                    $isPersistRelation
                );

                if ($this->fieldHelper->isSingleRelation($field)) {
                    // single relation
                    $relationEntity = $this->getObjectValue($entity, $fieldName);
                    if ($relationEntity) {
                        $relationItemData = $this->fieldHelper->getItemData($itemData, $fieldName);
                        $relationEntity = $this->processEntity(
                            $relationEntity,
                            $isFullRelation,
                            $isPersistRelation,
                            $relationItemData,
                            $searchContext,
                            true
                        );
                    }
                    $this->fieldHelper->setObjectValue($entity, $fieldName, $relationEntity);
                } elseif ($this->fieldHelper->isMultipleRelation($field)) {
                    // multiple relation
                    $relationCollection = $this->getObjectValue($entity, $fieldName);
                    if ($relationCollection instanceof Collection) {
                        $collectionItemData = $this->fieldHelper->getItemData($itemData, $fieldName);
                        $keysToRemove = [];
                        foreach ($relationCollection as $key => $collectionEntity) {
                            $entityItemData = $this->fieldHelper->getItemData(array_shift($collectionItemData));
                            $collectionEntity = $this->processEntity(
                                $collectionEntity,
                                $isFullRelation,
                                $isPersistRelation,
                                $entityItemData,
                                $searchContext,
                                true
                            );

                            if ($collectionEntity) {
                                $relationCollection->set($key, $collectionEntity);
                                $this->cacheInverseFieldRelation($entityName, $fieldName, $collectionEntity);
                            } else {
                                $keysToRemove[] = $key;
                            }
                        }

                        foreach ($keysToRemove as $key) {
                            $relationCollection->remove($key);
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function importExistingEntity($entity, $existingEntity, $itemData = null, array $excludedFields = [])
    {
        // Existing enum values shouldn't be modified. Just added to entity (collection).
        if (is_a($entity, AbstractEnumValue::class, true)) {
            return;
        }

        parent::importExistingEntity($entity, $existingEntity, $itemData, $excludedFields);
    }

    /**
     * {@inheritdoc}
     */
    protected function updateContextCounters($entity)
    {
        $identifier = $this->databaseHelper->getIdentifier($entity);
        if ($identifier || $this->newEntitiesHelper->getEntityUsage($this->getEntityHashKey($entity)) > 1) {
            $this->context->incrementUpdateCount();
        } else {
            $this->context->incrementAddCount();
        }
    }

    protected function isFieldExcluded($entityName, $fieldName, $itemData = null)
    {
        if (
            is_a($entityName, Product::class, true)
            && in_array($fieldName, ['variantLinks', 'parentVariantLinks', 'images'])
        ) {
            return true;
        }

        return parent::isFieldExcluded($entityName, $fieldName, $itemData);
    }

    protected function mapCollections(Collection $importedCollection, Collection $sourceCollection)
    {
    }

    protected function setLocalizationKeys($entity, array $field)
    {
    }

    protected function removeNotInitializedEntities($entity, array $field, array $relations)
    {
    }

    protected function generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation)
    {
        $fields = $this->fieldHelper->getRelations($entityName);

        if ($this->isFileValue($fields[$fieldName])) {
            $existingEntity = $this->findExistingEntity($entity);
            if ($existingEntity instanceof Product) {
                $file = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);

                if ($file instanceof File && $file->getOriginalFilename()) {
                    return [$file->getOriginalFilename() => $file];
                }
            }

            return parent::generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation);
        }

        if (!$this->isLocalizedFallbackValue($fields[$fieldName])) {
            return parent::generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation);
        }

        /** @var Collection $importedCollection */
        $importedCollection = $this->fieldHelper->getObjectValue($entity, $fieldName);
        if ($importedCollection->isEmpty()) {
            return parent::generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation);
        }

        $existingEntity = $this->findExistingEntity($entity);
        if ($existingEntity) {
            $searchContext = [];
            $sourceCollection = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);
            /** @var LocalizedFallbackValue $sourceValue */
            foreach ($sourceCollection as $sourceValue) {
                $localizationCode = LocalizationCodeFormatter::formatKey($sourceValue->getLocalization());
                $searchContext[$localizationCode] = $sourceValue;
            }

            return $searchContext;
        }

        return parent::generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation);
    }

    private function isFileValue(array $field): bool
    {
        return $this->fieldHelper->isRelation($field) && is_a($field['related_entity_name'], File::class, true);
    }
}
