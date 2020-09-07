<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\ImportExport\Strategy\EntityFieldImportStrategy;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

/**
 * Strategy to import attributes.
 */
class AttributeImportStrategy extends EntityFieldImportStrategy
{
    use ImportStrategyAwareHelperTrait;

    /**
     * @var FieldHelper
     */
    protected $fieldHelper;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @param FieldHelper $fieldHelper
     */
    public function setFieldHelper($fieldHelper)
    {
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * @param ConfigManager $configManager
     */
    public function setConfigManager($configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @param FieldConfigModel $entity
     *
     * @return object|null
     */
    protected function beforeProcessEntity($entity)
    {
        if (!$entity->getType()) {
            return null;
        }

        $extendProvider = $this->configManager->getProvider('extend');
        if ($entity->getId()) {
            $extendConfig = $extendProvider->getConfig($entity->getEntity()->getClassName(), $entity->getFieldName());

            if (ExtendScope::OWNER_SYSTEM === $extendConfig->get('owner')) {
                return null;
            }
        }

        if ($extendProvider->hasConfig($entity->getEntity()->getClassName(), $entity->getFieldName())) {
            $extendConfig = $extendProvider->getConfig($entity->getEntity()->getClassName(), $entity->getFieldName());
            if ($extendConfig->in('state', [ExtendScope::STATE_DELETE, ExtendScope::STATE_RESTORE])) {
                return null;
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function processEntity(FieldConfigModel $entity)
    {
        $supportedTypes = $this->fieldTypeProvider->getSupportedFieldTypes();
        $relationTypes = $this->fieldTypeProvider->getSupportedRelationTypes();

        if (
            !in_array($entity->getType(), $supportedTypes, true) &&
            !in_array($entity->getType(), $relationTypes, true)
        ) {
            $this->addErrors($this->translator->trans('oro.entity_config.import.message.invalid_field_type'));

            return null;
        }

        return $entity;
    }
}
