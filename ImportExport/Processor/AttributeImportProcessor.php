<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;
use Oro\Bundle\ProductBundle\Entity\Product;

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
        $originalCode = $item['code'];

        if ($this->dataConverter) {
            $item = $this->dataConverter->convertToImportFormat($item, false);
        }

        if (null === $item['type']) {
            return null;
        }

        $this->context->setValue('originalFieldName', $originalCode);

        $object = $this->serializer->deserialize(
            $item,
            $this->getEntityName(),
            null,
            $this->context->getConfiguration()
        );

        if ($this->strategy) {
            $object = $this->strategy->process($object);
        }

        if ($object instanceof FieldConfigModel) {
            $this->fieldNameMapping[$object->getFieldName()] = $originalCode;
            $this->fieldTypeMapping[$object->getFieldName()] = $item['type'];
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
     * @param array $item
     * @param string $fieldName
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
     * @param array $item
     * @param string $fieldName
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
        $this->removeUnprocessed();

        $this->cacheProvider->save('attribute_attributeLabels', $this->attributeLabels);
        $this->cacheProvider->save('attribute_optionLabels', $this->optionLabels);
        $this->cacheProvider->save('attribute_fieldNameMapping', $this->fieldNameMapping);
        $this->cacheProvider->save('attribute_fieldTypeMapping', $this->fieldTypeMapping);
        unset($this->attributeLabels);
        unset($this->optionLabels);
        unset($this->fieldNameMapping);
        unset($this->fieldTypeMapping);
    }

    protected function removeUnprocessed()
    {
        $fields = $this->fieldHelper->getFields(Product::class, true);
        $importExportProvider = $this->configManager->getProvider('importexport');
        $extendProvider = $this->configManager->getProvider('extend');

        foreach ($fields as $field) {
            if (array_key_exists($field['name'], $this->fieldNameMapping)) {
                continue;
            }

            if (false === $this->configManager->hasConfig(Product::class, $field['name'])) {
                continue;
            }

            $extendConfig = $extendProvider->getConfig(Product::class, $field['name']);
            if (ExtendScope::STATE_ACTIVE !== $extendConfig->get('state')) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);
            if ('akeneo' !== $importExportConfig->get('source')) {
                continue;
            }

            $fieldConfig = $extendProvider->getConfig(Product::class, $field['name']);
            if (ExtendScope::STATE_DELETE === $fieldConfig->get('state')) {
                continue;
            }

            $fieldConfig->set('state', ExtendScope::STATE_DELETE);
            $this->configManager->persist($fieldConfig);
            $this->contextRegistry->getByStepExecution($this->stepExecution)->incrementDeleteCount();

            $entityConfig = $extendProvider->getConfig(Product::class);
            $entityConfig->set('upgradeable', true);
            $this->configManager->persist($entityConfig);
        }

        $this->configManager->flush();
        $this->configManager->clear();
    }

    /**
     * @param FieldHelper $fieldHelper
     */
    public function setFieldHelper(FieldHelper $fieldHelper): void
    {
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * @param string $className
     */
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
