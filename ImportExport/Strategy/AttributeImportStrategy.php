<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Inflector\Inflector;
use Oro\Bundle\AkeneoBundle\Tools\FieldConfigModelFieldNameGenerator;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\ImportExport\Strategy\EntityFieldImportStrategy;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;

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

        if ($entity->getId()) {
            $extendProvider = $this->configManager->getProvider('extend');
            $extendConfig = $extendProvider->getConfig($entity->getEntity()->getClassName(), $entity->getFieldName());

            if (ExtendScope::OWNER_SYSTEM === $extendConfig->get('owner')) {
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

        $entity = $this->checkClassProperties($entity);

        return $entity;
    }

    private function checkClassProperties(FieldConfigModel $entity): ?FieldConfigModel
    {
        $importExportProvider = $this->configManager->getProvider('importexport');
        $entityCode = $this->context->getValue('originalFieldName');

        // @BC: Keep Akeneo_Aken_1706289854 working
        $fieldName = FieldConfigModelFieldNameGenerator::generate(
            sprintf('%s_%s', $entity->getFieldName(), $entity->getType())
        );
        if ($importExportProvider->hasConfig($entity->getEntity()->getClassName(), $fieldName)) {
            $entity->setFieldName($fieldName);

            return $entity;
        }

        $fields = $this->fieldHelper->getFields($entity->getEntity()->getClassName(), true);
        foreach ($fields as $field) {
            if (
                $field['name'] !== $entityCode &&
                $field['name'] !== $this->makeSingular($entityCode) &&
                $field['name'] !== $this->makePlural($entityCode)
            ) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(
                $entity->getEntity()->getClassName(),
                $field['name']
            );

            // Field should be updated
            if ('akeneo' === $importExportConfig->get('source')) {
                return $entity;
            }

            // Field should be skipped
            if (
                $entity->getType() === $field['type'] ||
                (RelationType::MANY_TO_MANY === $entity->getType() && RelationType::TO_MANY === $field['type'])
            ) {
                return null;
            }
        }

        return $entity;
    }

    protected function makeSingular(string $value): string
    {
        return Inflector::singularize(Inflector::camelize($value));
    }

    protected function makePlural(string $value): string
    {
        return Inflector::pluralize(Inflector::camelize($value));
    }
}
