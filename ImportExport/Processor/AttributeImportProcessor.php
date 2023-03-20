<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

/**
 * Converts data to import format, processes entity.
 */
class AttributeImportProcessor extends StepExecutionAwareImportProcessor implements MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

    /** @var string */
    private $entityConfigModelClassName;

    /** @var ConfigManager */
    private $configManager;

    /** @var FieldHelper */
    private $fieldHelper;

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
            $this->memoryCacheProvider->get(
                'attribute_fieldNameMapping_' . $object->getFieldName(),
                function () use ($code) {
                    return $code;
                }
            );
            $this->memoryCacheProvider->get(
                'attribute_fieldTypeMapping_' . $object->getFieldName(),
                function () use ($type) {
                    return $type;
                }
            );

            $itemData = $this->context->getValue('itemData');

            $this->updateAttributeLabelTranslationContext($itemData, $object->getFieldName());
            $this->updateOptionLabelTranslationContext($itemData, $object->getFieldName());
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

        $this->memoryCacheProvider->get(
            'attribute_attributeLabels_' . $fieldName,
            function () use ($item) {
                return $item['translatedLabels'];
            }
        );
    }

    /**
     * Set option labels in context for writer.
     */
    private function updateOptionLabelTranslationContext(array &$item, string $fieldName)
    {
        if (empty($item['options'])) {
            return;
        }

        $optionLabels = [];

        foreach ($item['options'] as $option) {
            if (empty($option['translatedLabels'])) {
                continue;
            }

            $optionLabels[] = [
                'default' => $option['defaultLabel'],
                'translations' => $option['translatedLabels'],
            ];
        }

        $this->memoryCacheProvider->get(
            'attribute_optionLabels_' . $fieldName,
            function () use ($optionLabels) {
                return $optionLabels;
            }
        );
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

    public function setImportExportContext(ContextInterface $context): void
    {
        $context->setValue('entity_id', $this->configManager->getConfigModelId($this->entityConfigModelClassName));

        parent::setImportExportContext($context);
    }
}
