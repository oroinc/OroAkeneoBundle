<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\ProductBundle\Entity\Brand;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\ImportExport\Strategy\ProductStrategy;

/**
 * Strategy to import products.
 */
class ProductImportStrategy extends ProductStrategy implements ExistingEntityAwareInterface
{
    use ImportStrategyAwareHelperTrait;
    use OwnerTrait;

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

        $this->existingProducts = [];

        $this->clearOwnerCache();

        $this->databaseHelper->onClear();

        parent::close();
    }

    protected function beforeProcessEntity($entity)
    {
        $this->setOwner($entity);

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        if ($entity instanceof Product && $entity->getCategory() && !$entity->getCategory()->getId()) {
            $entity->setCategory(null);

            $categoryCodes = (array)$this->fieldHelper->getItemData(
                $this->context->getValue('rawItemData'),
                'categories'
            );

            foreach (array_filter($categoryCodes) as $categoryCode) {
                $category = $this->databaseHelper->findOneBy(
                    Category::class,
                    ['akeneo_code' => $categoryCode, 'channel' => $this->context->getOption('channel')]
                );

                if ($category instanceof Category) {
                    $entity->setCategory($category);

                    break;
                }
            }
        }

        if ($entity instanceof Product && !$entity->getInventoryStatus()) {
            $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName('prod_inventory_status');
            $inventoryStatus = $this->findEntityByIdentityValues(
                $inventoryStatusClassName,
                ['id' => Product::INVENTORY_STATUS_IN_STOCK]
            );
            $entity->setInventoryStatus($inventoryStatus);
        }

        $this->existingProducts = [];

        $result = parent::afterProcessEntity($entity);
        if (!$result && $entity) {
            $this->processValidationErrors($entity, []);
        }

        return $result;
    }

    protected function populateOwner(Product $entity)
    {
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Product && array_key_exists($entity->getSku(), $this->existingProducts)) {
            return $this->existingProducts[$entity->getSku()];
        }

        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if ($entity instanceof Brand && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Brand::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

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
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if ($entity instanceof Brand && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Brand::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        $entity = parent::findExistingEntityByIdentityFields($entity, $searchContext);

        if ($entity instanceof Product) {
            $this->existingProducts[$entity->getSku()] = $entity;
        }

        return $entity;
    }

    public function getExistingEntity($entity, array $searchContext = [])
    {
        return parent::findExistingEntity($entity, $searchContext);
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
                        foreach ($relationCollection as $collectionEntity) {
                            $entityItemData = $this->fieldHelper->getItemData(array_shift($collectionItemData));
                            $existingCollectionEntity = $this->processEntity(
                                $collectionEntity,
                                $isFullRelation,
                                $isPersistRelation,
                                $entityItemData,
                                $searchContext,
                                true
                            );

                            if ($existingCollectionEntity) {
                                if (!$relationCollection->contains($existingCollectionEntity)) {
                                    $relationCollection->removeElement($collectionEntity);
                                    $relationCollection->add($existingCollectionEntity);
                                }

                                $this->cacheInverseFieldRelation($entityName, $fieldName, $existingCollectionEntity);
                            }
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
        $excludeProductFields = [
            'variantLinks',
            'parentVariantLinks',
            'images',
            'slugs',
            'slugPrototypes',
            'slugPrototypesWithRedirect',
            'inventory_status',
        ];

        if (is_a($entityName, Product::class, true) && in_array($fieldName, $excludeProductFields)) {
            return true;
        }

        $allowedProductFields = [
            'brand',
        ];

        if (is_a($entityName, Product::class, true) && in_array($fieldName, $allowedProductFields)) {
            return false;
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
        $searchContext = parent::generateSearchContextForRelationsUpdate(
            $entity,
            $entityName,
            $fieldName,
            $isPersistRelation
        );

        $fields = $this->fieldHelper->getRelations($entityName);

        if (!$this->isLocalizedFallbackValue($fields[$fieldName])) {
            return $searchContext;
        }

        /** @var Collection $importedCollection */
        $importedCollection = $this->fieldHelper->getObjectValue($entity, $fieldName);
        if ($importedCollection->isEmpty()) {
            return $searchContext;
        }

        $existingEntity = $this->findExistingEntity($entity);
        if ($existingEntity) {
            $searchContext = [];
            $sourceCollection = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);
            /** @var LocalizedFallbackValue $sourceValue */
            foreach ($sourceCollection as $sourceValue) {
                $localizationCode = LocalizationCodeFormatter::formatName($sourceValue->getLocalization());
                $searchContext[$localizationCode] = $sourceValue;
            }

            return $searchContext;
        }

        return $searchContext;
    }

    protected function validateBeforeProcess($entity)
    {
        $validationErrors = $this->strategyHelper->validateEntity($entity, null, ['import_field_type_akeneo']);
        if ($validationErrors) {
            $this->context->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $this->context);

            return null;
        }

        return $entity;
    }
}
