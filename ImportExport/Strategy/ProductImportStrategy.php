<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
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
     * @var array
     */
    protected $subclassCache = [];

    public function close()
    {
        $this->subclassCache = [];
        $this->reflectionProperties = [];
        $this->cachedEntities = [];
        $this->cachedInverseSingleRelations = [];
        $this->cachedExistingEntities = [];
        $this->cachedInverseMultipleRelations = [];

        $this->databaseHelper->onClear();

        parent::close();
    }

    protected function beforeProcessEntity($entity)
    {
        if ($entity->getCategory() instanceof Category) {
            $category = $this->findExistingEntity($entity->getCategory());
            $entity->setCategory($category);
        }

        /** @var Product $existingProduct */
        $existingProduct = $this->findExistingEntity($entity);
        if ($existingProduct) {
            $entity->getVariantLinks()->clear();
            foreach ($existingProduct->getVariantLinks() as $variantLink) {
                $entity->getVariantLinks()->add($variantLink);
            }
            $entity->getParentVariantLinks()->clear();
            foreach ($existingProduct->getParentVariantLinks() as $variantLink) {
                $entity->getParentVariantLinks()->add($variantLink);
            }

            $fields = $this->fieldHelper->getRelations(Product::class);
            foreach ($fields as $field) {
                if ($this->isLocalizedFallbackValue($field)) {
                    $fieldName = $field['name'];
                    $this->mapCollections(
                        $this->fieldHelper->getObjectValue($entity, $fieldName),
                        $this->fieldHelper->getObjectValue($existingProduct, $fieldName)
                    );
                }
            }

            $category = $existingProduct->getCategory();
            $categories = array_filter((array)$this->context->getValue('rawItemData')['categories'] ?? []);
            if ($category && $category->getAkeneoCode() && in_array($category->getAkeneoCode(), $categories)) {
                $entity->setCategory($category);
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function mapCollections(Collection $importedCollection, Collection $sourceCollection)
    {
        if ($importedCollection->isEmpty()) {
            return;
        }

        if ($sourceCollection->isEmpty()) {
            return;
        }

        $sourceCollection = $sourceCollection->toArray();
        $sourceCollectionArray = [];

        /** @var LocalizedFallbackValue $sourceValue */
        foreach ($sourceCollection as $sourceValue) {
            $key = LocalizationCodeFormatter::formatKey($sourceValue->getLocalization()) ??
                LocalizationCodeFormatter::DEFAULT_LOCALIZATION;
            $sourceCollectionArray[$key] = $sourceValue->getId();
        }

        foreach ($importedCollection as $importedValue) {
            $key = LocalizationCodeFormatter::formatKey($importedValue->getLocalization()) ??
                LocalizationCodeFormatter::DEFAULT_LOCALIZATION;
            if (array_key_exists($key, $sourceCollectionArray)) {
                $this->fieldHelper->setObjectValue($importedValue, 'id', $sourceCollectionArray[$key]);
            }
        }
    }

    protected function setLocalizationKeys($entity, array $field)
    {
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $entity->getChannel()]
            );
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $entity->getChannel()]
            );
        }

        return parent::findExistingEntityByIdentityFields($entity, $searchContext);
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
        $entityName = ClassUtils::getClass($entity);
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
        $entityClass = ClassUtils::getClass($entity);

        if (true === isset($this->subclassCache[$entityClass]) && true === $this->subclassCache[$entityClass]) {
            return;
        }
        if (false === isset($this->subclassCache[$entityClass])) {
            $reflectionClass = new \ReflectionClass($entity);
            $this->subclassCache[$entityClass] = $reflectionClass->isSubclassOf(AbstractEnumValue::class);

            if (true === $this->subclassCache[$entityClass]) {
                return;
            }
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
}
