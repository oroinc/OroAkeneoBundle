<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;

/**
 * Strategy to import categories.
 */
class CategoryImportStrategy extends LocalizedFallbackValueAwareStrategy implements ClosableInterface
{
    use ImportStrategyAwareHelperTrait;
    use OwnerTrait;

    /**
     * @var Category[]
     *
     * Cache existing category request in scope of a single row processing to avoid excess DB queries
     */
    private $existingCategories = [];

    /** @var int */
    private $rootCategoryId;

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];

        $this->existingCategories = [];
        $this->rootCategoryId = null;

        $this->clearOwnerCache();

        $this->databaseHelper->onClear();
    }

    protected function beforeProcessEntity($entity)
    {
        $this->setOwner($entity);

        if ($entity instanceof Category) {
            $parent = $entity->getParentCategory();
            if ($parent instanceof Category && !$parent->getId()) {
                $existingParent = $this->findExistingEntity($parent) ?? $this->getRootCategory();
                $entity->setParentCategory($existingParent);
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        $this->existingCategories = [];

        return parent::afterProcessEntity($entity);
    }

    private function getRootCategory()
    {
        if (null === $this->rootCategoryId) {
            $channelId = $this->context->getOption('channel');
            $channel = $this->doctrineHelper->getEntityRepository(Channel::class)->find($channelId);

            $rootCategoryId = false;
            if ($channel->getTransport()->getRootCategory()) {
                $rootCategoryId = $channel->getTransport()->getRootCategory()->getId();
            }
            $this->rootCategoryId = $rootCategoryId;
        }

        if ($this->rootCategoryId) {
            return $this->doctrineHelper->getEntityReference(Category::class, $this->rootCategoryId);
        }

        return null;
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            $category = $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );

            if ($category) {
                $this->existingCategories[$entity->getAkeneoCode()] = $category;
            }

            return $category;
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            $category = $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );

            if ($category) {
                $this->existingCategories[$entity->getAkeneoCode()] = $category;
            }

            return $category;
        }

        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
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
        $excludeCategoryFields = [
            'childCategories',
            'slugs',
            'slugPrototypes',
            'slugPrototypesWithRedirect',
        ];

        if (is_a($entityName, Category::class, true) && in_array($fieldName, $excludeCategoryFields)) {
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
                $localizationCode = LocalizationCodeFormatter::formatName($sourceValue->getLocalization());
                $searchContext[$localizationCode] = $sourceValue;
            }

            return $searchContext;
        }

        return parent::generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation);
    }
}
