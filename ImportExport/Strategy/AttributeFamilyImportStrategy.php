<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * Strategy to import attribute families.
 */
class AttributeFamilyImportStrategy extends LocalizedFallbackValueAwareStrategy implements ClosableInterface
{
    use LocalizedFallbackValueAwareStrategyTrait;
    use StrategyRelationsTrait;
    use StrategyValidationTrait;

    private const GROUP_CODE_GENERAL = 'general';
    private const GROUP_CODE_IMAGES = 'images';

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
    }

    public function beforeProcessEntity($entity)
    {
        $this->removeInactiveAttributes($entity);
        $this->setSystemAttributes($entity);

        return parent::beforeProcessEntity($entity);
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
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

        return $this->findExistingEntityTrait($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
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

        return $this->findExistingEntityByIdentityFieldsTrait($entity, $searchContext);
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

    protected function setSystemAttributes(AttributeFamily $entity): void
    {
        $defaultGroup = $this->getDefaultGroup($entity);
        $imagesGroup = $this->getImagesGroup($entity);

        $systemAttributes = $this->attributeManager->getSystemAttributesByClass($entity->getEntityClass());

        foreach ($systemAttributes as $systemAttribute) {
            $attributeGroupRelation = $this->getAttributeGroupRelation($entity, $systemAttribute);
            if (!$attributeGroupRelation) {
                $attributeGroupRelation = new AttributeGroupRelation();
                $attributeGroupRelation->setEntityConfigFieldId($systemAttribute->getId());
                $defaultGroup->addAttributeRelation($attributeGroupRelation);
            }

            if ($systemAttribute->getFieldName() === 'images') {
                $defaultGroup->removeAttributeRelation($attributeGroupRelation);
                $imagesGroup->addAttributeRelation($attributeGroupRelation);
            }
        }

        $entity->addAttributeGroup($defaultGroup);
        $entity->addAttributeGroup($imagesGroup);
    }

    protected function getAttributeGroupRelation(
        AttributeFamily $attributeFamily,
        FieldConfigModel $attribute
    ): ?AttributeGroupRelation {
        foreach ($attributeFamily->getAttributeGroups() as $attributeGroup) {
            foreach ($attributeGroup->getAttributeRelations() as $relation) {
                if ($relation->getEntityConfigFieldId() == $attribute->getId()) {
                    return $relation;
                }
            }
        }

        return null;
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
    protected function getDefaultGroup(AttributeFamily $entity): AttributeGroup
    {
        $defaultGroup = $entity->getAttributeGroup(self::GROUP_CODE_GENERAL);

        if (!$defaultGroup) {
            $defaultGroup = new AttributeGroup();
            $defaultGroup->setCode(self::GROUP_CODE_GENERAL);
            $defaultGroup->setAkeneoCode('default');
            $defaultGroup->setDefaultLabel(
                $this->translator->trans('oro.entity_config.form.default_group_label')
            );
        }

        return $defaultGroup;
    }

    /**
     * Gets existing images attribute group or creates new one.
     */
    protected function getImagesGroup(AttributeFamily $entity): AttributeGroup
    {
        $imagesGroup = $entity->getAttributeGroup(self::GROUP_CODE_IMAGES);

        if (!$imagesGroup) {
            $imagesGroup = new AttributeGroup();
            $imagesGroup->setCode(self::GROUP_CODE_IMAGES);
            $imagesGroup->setAkeneoCode(self::GROUP_CODE_IMAGES);
            $imagesGroup->setDefaultLabel($this->translator->trans('oro.product.images.label'));
        }

        return $imagesGroup;
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
}
