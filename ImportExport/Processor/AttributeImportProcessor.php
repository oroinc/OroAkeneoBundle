<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

/**
 * Converts data to import format, processes entity.
 */
class AttributeImportProcessor extends StepExecutionAwareImportProcessor
{
    use CacheProviderAwareProcessor;

    /** @var string */
    private $entityConfigModelClassName;

    /** @var ConfigManager */
    private $configManager;

    /** @var FieldHelper */
    private $fieldHelper;

    /** @var array */
    private $attributeLabels = [];

    /** @var array */
    private $optionLabels = [];

    /** @var array */
    private $fieldNameMapping = [];

    /** @var array */
    private $fieldTypeMapping = [];

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        $code = $item['code'];
        $type = $item['type'];

        $this->context->setValue('originalFieldName', $code);

        $object = parent::process($item);
        if ($object instanceof FieldConfigModel) {
            $this->fieldNameMapping[$object->getFieldName()] = $code;
            $this->fieldTypeMapping[$object->getFieldName()] = $type;
            $this->cacheProvider->save('attribute_fieldNameMapping', $this->fieldNameMapping);
            $this->cacheProvider->save('attribute_fieldTypeMapping', $this->fieldTypeMapping);

            $this->updateAttributeLabelTranslationContext($item, $object->getFieldName());
            $this->cacheProvider->save('attribute_attributeLabels', $this->attributeLabels);

            $this->updateOptionLabelTranslationContext($item, $object->getFieldName());
            $this->cacheProvider->save('attribute_optionLabels', $this->optionLabels);
        }

        return $object;
    }

    /**
     * Set attribute labels in context for writer.
     */
    private function updateAttributeLabelTranslationContext(array &$item, string $fieldName)
    {
        if (empty($item['translatedLabels'])) {
            return;
        }

        $this->attributeLabels[$fieldName] = $item['translatedLabels'];
    }

    /**
     * Set option labels in context for writer.
     */
    private function updateOptionLabelTranslationContext(array &$item, string $fieldName)
    {
        if (empty($item['options'])) {
            return;
        }

        foreach ($item['options'] as $option) {
            if (empty($option['translatedLabels'])) {
                continue;
            }

            $this->optionLabels[$fieldName][] = [
                'default' => $option['defaultLabel'],
                'translations' => $option['translatedLabels'],
            ];
        }
    }

    public function initialize()
    {
        $this->attributeLabels = [];
        $this->optionLabels = [];
        $this->fieldNameMapping = [];
        $this->fieldTypeMapping = [];
    }

    public function flush()
    {
        $this->cacheProvider->save('attribute_attributeLabels', $this->attributeLabels);
        $this->cacheProvider->save('attribute_optionLabels', $this->optionLabels);
        $this->cacheProvider->save('attribute_fieldNameMapping', $this->fieldNameMapping);
        $this->cacheProvider->save('attribute_fieldTypeMapping', $this->fieldTypeMapping);
        $this->attributeLabels = null;
        $this->optionLabels = null;
        $this->fieldNameMapping = null;
        $this->fieldTypeMapping = null;
    }

    public function setFieldHelper(FieldHelper $fieldHelper): void
    {
        $this->fieldHelper = $fieldHelper;
    }

    public function setEntityConfigModelClassName(string $className)
    {
        $this->entityConfigModelClassName = $className;
    }

    /**
     * @param ConfigManager $configManager
     */
    public function setConfigManager($configManager)
    {
        $this->configManager = $configManager;
    }

    public function setImportExportContext(ContextInterface $context)
    {
        $context->setValue('entity_id', $this->configManager->getConfigModelId($this->entityConfigModelClassName));

        parent::setImportExportContext($context);
    }
}
