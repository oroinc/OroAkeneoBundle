<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
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

        /** @var AttributeFamily $existingEntity */
        $existingEntity = $this->findExistingEntity($entity);
        if (!$existingEntity) {
            return parent::beforeProcessEntity($entity);
        }

        $fields = $this->fieldHelper->getRelations(AttributeFamily::class);
        foreach ($fields as $field) {
            if ($this->isLocalizedFallbackValue($field)) {
                $fieldName = $field['name'];
                $this->mapCollections(
                    $this->fieldHelper->getObjectValue($entity, $fieldName),
                    $this->fieldHelper->getObjectValue($existingEntity, $fieldName)
                );
            }
        }

        $fields = $this->fieldHelper->getRelations(AttributeGroup::class);
        foreach ($entity->getAttributeGroups() as $attributeGroup) {
            $existingAttributeGroup = null;
            foreach ($existingEntity->getAttributeGroups() as $possibleAttributeGroup) {
                if ($possibleAttributeGroup->getCode() === $attributeGroup->getCode()) {
                    $existingAttributeGroup = $possibleAttributeGroup;
                }
            }
            if (!$existingAttributeGroup) {
                continue;
            }

            foreach ($fields as $field) {
                if ($this->isLocalizedFallbackValue($field)) {
                    $fieldName = $field['name'];
                    $this->mapCollections(
                        $this->fieldHelper->getObjectValue($attributeGroup, $fieldName),
                        $this->fieldHelper->getObjectValue($existingAttributeGroup, $fieldName)
                    );
                }
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation)
    {
        return ConfigurableAddOrReplaceStrategy::generateSearchContextForRelationsUpdate(
            $entity,
            $entityName,
            $fieldName,
            $isPersistRelation
        );
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        return ConfigurableAddOrReplaceStrategy::findExistingEntity($entity, $searchContext);
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
}
