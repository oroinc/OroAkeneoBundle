<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\ImportExport\Helper\DefaultOwnerHelper;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * Strategy to import attribute families.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class AttributeFamilyImportStrategy extends LocalizedFallbackValueAwareStrategy
{
    use ImportStrategyAwareHelperTrait;

    private const GROUP_CODE_GENERAL = 'general';

    /**
     * @var DefaultOwnerHelper
     */
    protected $ownerHelper;

    /**
     * @var AttributeManager
     */
    protected $attributeManager;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @param DefaultOwnerHelper $ownerHelper
     */
    public function setOwnerHelper($ownerHelper)
    {
        $this->ownerHelper = $ownerHelper;
    }

    public function setConfigManager(ConfigManager $configManager): void
    {
        $this->configManager = $configManager;
    }

    public function setAttributeManager(AttributeManager $attributeManager)
    {
        $this->attributeManager = $attributeManager;
    }

    /**
     * @param AttributeFamily $entity
     *
     * @return object
     */
    public function beforeProcessEntity($entity)
    {
        $this->removeInactiveAttributes($entity);
        $this->setSystemAttributes($entity);
        $this->setOwner($entity);

        return parent::beforeProcessEntity($entity);
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        if (is_a($entity, AttributeGroup::class) && !$this->processingEntity->getId()) {
            return null;
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if (is_a($entity, $this->localizedFallbackValueClass, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        if (is_a($entity, AttributeGroup::class) && !$this->processingEntity->getId()) {
            return null;
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

                if (ExtendScope::STATE_ACTIVE !== $extendConfig->get('state')) {
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
     * Sets owner.
     */
    private function setOwner(AttributeFamily $entity)
    {
        if (false === $this->ownerChecker->isOwnerCanBeSet($entity)) {
            return;
        }

        $channelId = $this->context->getOption('channel');
        $channel = $this->doctrineHelper->getEntityRepository(Channel::class)->find($channelId);
        $this->ownerHelper->populateChannelOwner($entity, $channel);
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
