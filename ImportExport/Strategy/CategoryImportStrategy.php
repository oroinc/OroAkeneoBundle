<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;

/**
 * Strategy to import categories.
 */
class CategoryImportStrategy extends LocalizedFallbackValueAwareStrategy
{
    use ImportStrategyAwareHelperTrait;

    protected function beforeProcessEntity($entity)
    {
        if ($entity instanceof Category) {
            $parent = $entity->getParentCategory();
            if ($parent instanceof Category) {
                /** @var Category $parent */
                $parent = $this->findExistingEntity($parent);
                $entity->setParentCategory($parent);
            }
        }

        return parent::beforeProcessEntity($entity);
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
     * @param array|null $itemData
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

                        $this->cacheInverseFieldRelation($entityName, $fieldName, $relationEntity);
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
