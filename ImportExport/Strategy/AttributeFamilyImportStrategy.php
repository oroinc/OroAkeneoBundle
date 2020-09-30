<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * Strategy to import attribute families.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class AttributeFamilyImportStrategy extends LocalizedFallbackValueAwareStrategy implements ClosableInterface
{
    use ImportStrategyAwareHelperTrait;
    use OwnerTrait;

    private const GROUP_CODE_GENERAL = 'general';

    /**
     * @var AttributeManager
     */
    protected $attributeManager;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    public function setConfigManager(ConfigManager $configManager): void
    {
        $this->configManager = $configManager;
    }

    public function setAttributeManager(AttributeManager $attributeManager)
    {
        $this->attributeManager = $attributeManager;
    }

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];

        $this->databaseHelper->onClear();

        $this->clearOwnerCache();
    }

    public function beforeProcessEntity($entity)
    {
        $this->removeInactiveAttributes($entity);
        $this->setSystemAttributes($entity);
        $this->setOwner($entity);

        return $entity;
    }

    protected function afterProcessEntity($entity)
    {
        return $entity;
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        if (is_a($entity, AttributeGroup::class)) {
            $family = $this->findExistingEntity($entity->getAttributeFamily());
            if ($family instanceof AttributeFamily) {
                foreach ($family->getAttributeGroups() as $attributeGroup) {
                    if ($attributeGroup->getAkeneoCode() === $entity->getAkeneoCode()) {
                        return $attributeGroup;
                    }
                }
            }

            return null;
        }

        if ($entity instanceof File) {
            return $searchContext[$entity->getOriginalFilename()] ?? null;
        }

        if ($entity instanceof FileItem) {
            return $searchContext[$entity->getFile()->getOriginalFilename()] ?? null;
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        if (is_a($entity, AttributeGroup::class)) {
            $family = $this->findExistingEntity($entity->getAttributeFamily());
            if ($family instanceof AttributeFamily) {
                foreach ($family->getAttributeGroups() as $attributeGroup) {
                    if ($attributeGroup->getAkeneoCode() === $entity->getAkeneoCode()) {
                        return $attributeGroup;
                    }
                }
            }

            return null;
        }

        if ($entity instanceof File) {
            return $searchContext[$entity->getOriginalFilename()] ?? null;
        }

        if ($entity instanceof FileItem) {
            return $searchContext[$entity->getFile()->getOriginalFilename()] ?? null;
        }

        return parent::findExistingEntityByIdentityFields($entity, $searchContext);
    }

    private function removeInactiveAttributes(AttributeFamily $entity)
    {
        $extendProvider = $this->configManager->getProvider('extend');

        foreach ($entity->getAttributeGroups() as $attributeGroup) {
            foreach ($attributeGroup->getAttributeRelations() as $attributeRelation) {
                $fieldConfigModel = $this->getFieldConfigModel($attributeRelation->getEntityConfigFieldId());
                if (!$fieldConfigModel) {
                    continue;
                }

                $extendConfig = $extendProvider->getConfig(Product::class, $fieldConfigModel->getFieldName());

                if (!$extendConfig->is('state', ExtendScope::STATE_ACTIVE)) {
                    $attributeGroup->removeAttributeRelation($attributeRelation);
                }
            }
        }
    }

    /**
     * @return FieldConfigModel|null
     */
    private function getFieldConfigModel(int $id)
    {
        return $this->doctrineHelper
            ->getEntityRepository(FieldConfigModel::class)
            ->find($id);
    }

    private function setSystemAttributes(AttributeFamily $entity): void
    {
        $defaultGroup = $this->getDefaultGroup($entity);
        $systemAttributes = $this->attributeManager->getSystemAttributesByClass($entity->getEntityClass());

        foreach ($systemAttributes as $systemAttribute) {
            if (false === $this->containsAttribute($entity, $systemAttribute)) {
                $attributeGroupRelation = new AttributeGroupRelation();
                $attributeGroupRelation->setEntityConfigFieldId($systemAttribute->getId());

                $defaultGroup->addAttributeRelation($attributeGroupRelation);
            }
        }

        $defaultGroup->setDefaultLabel(
            $this->translator->trans('oro.entity_config.form.default_group_label')
        );
        $entity->addAttributeGroup($defaultGroup);
    }

    private function containsAttribute(AttributeFamily $attributeFamily, FieldConfigModel $attribute): bool
    {
        foreach ($attributeFamily->getAttributeGroups() as $attributeGroup) {
            foreach ($attributeGroup->getAttributeRelations() as $relation) {
                if ($relation->getEntityConfigFieldId() == $attribute->getId()) {
                    return true;
                }
            }
        }

        return false;
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
     * @param object $entity
     * @param object $existingEntity
     * @param mixed|array|null $itemData
     */
    protected function importExistingEntity(
        $entity,
        $existingEntity,
        $itemData = null,
        array $excludedFields = []
    ) {
        parent::importExistingEntity($entity, $existingEntity, $itemData, $excludedFields);

        if (true === $entity instanceof AttributeGroup) {
            $this->processAttributeRelations($entity, $existingEntity);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function processAttributeRelations(AttributeGroup $entity, AttributeGroup $existingEntity): void
    {
        $toBeRemoved = [];

        foreach ($existingEntity->getAttributeRelations() as $existingRelation) {
            $match = false;
            foreach ($entity->getAttributeRelations() as $newRelation) {
                if ($existingRelation->getEntityConfigFieldId() == $newRelation->getEntityConfigFieldId()) {
                    $match = true;
                }
            }

            if (false === $match) {
                $toBeRemoved[] = $existingRelation;
            }
        }

        foreach ($toBeRemoved as $relation) {
            $existingEntity->removeAttributeRelation($relation);
        }

        foreach ($entity->getAttributeRelations() as $newRelation) {
            $match = false;
            foreach ($existingEntity->getAttributeRelations() as $existingRelation) {
                if ($existingRelation->getEntityConfigFieldId() == $newRelation->getEntityConfigFieldId()) {
                    $match = true;
                }
            }
            if (false === $match) {
                $existingEntity->addAttributeRelation($newRelation);
            }
        }
    }

    /**
     * Increment context counters.
     *
     * @param AttributeFamily $entity
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

    /**
     * Gets existing default attribute group or creates new one.
     */
    private function getDefaultGroup(AttributeFamily $entity): AttributeGroup
    {
        $defaultGroup = $entity->getAttributeGroup(self::GROUP_CODE_GENERAL);

        if (!$defaultGroup) {
            $defaultGroup = new AttributeGroup();
            $defaultGroup->setCode(self::GROUP_CODE_GENERAL);
            $defaultGroup->setAkeneoCode('default');
        }

        return $defaultGroup;
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

    protected function combineIdentityValues($entity, $entityClass, array $searchContext)
    {
        if (is_a($entity, AttributeGroup::class)) {
            return [
                'attributeFamily' => $entity->getAttributeFamily()->getCode(),
                'code' => $entity->getCode(),
            ];
        }

        return parent::combineIdentityValues($entity, $entityClass, $searchContext);
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

        if ($this->isFileValue($fields[$fieldName])) {
            $existingEntity = $this->findExistingEntity($entity);
            if ($existingEntity) {
                $file = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);

                if ($file instanceof File && $file->getOriginalFilename()) {
                    return [$file->getOriginalFilename() => $file];
                }
            }

            return $searchContext;
        }

        if ($this->isFileItemValue($fields[$fieldName])) {
            $existingEntity = $this->findExistingEntity($entity);
            if ($existingEntity) {
                $collection = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);

                foreach ($collection as $fileItem) {
                    if ($fileItem instanceof FileItem && $fileItem->getFile()->getOriginalFilename()) {
                        $searchContext[$fileItem->getFile()->getOriginalFilename()] = $fileItem;
                    }
                }
            }

            return $searchContext;
        }

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

    private function isFileValue(array $field): bool
    {
        return $this->fieldHelper->isRelation($field) && is_a($field['related_entity_name'], File::class, true);
    }

    private function isFileItemValue(array $field): bool
    {
        return $this->fieldHelper->isRelation($field) && is_a($field['related_entity_name'], FileItem::class, true);
    }
}
