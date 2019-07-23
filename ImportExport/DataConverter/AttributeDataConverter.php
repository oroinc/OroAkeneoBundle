<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\AkeneoBundle\Tools\AttributeTypeConverter;
use Oro\Bundle\AkeneoBundle\Tools\FieldConfigModelFieldNameGenerator;
use Oro\Bundle\EntityConfigBundle\ImportExport\DataConverter\EntityFieldDataConverter;

class AttributeDataConverter extends EntityFieldDataConverter
{
    use AkeneoIntegrationTrait;

    private const ENTITY_LABEL_MAX_LENGTH = 50;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $importedRecord['type'] = AttributeTypeConverter::convert($importedRecord['type']);
        $importedRecord['fieldName'] = FieldConfigModelFieldNameGenerator::generate($importedRecord['code']);
        $importedRecord['entity:id'] = (int)$this->getContext()->getValue('entity_id');
        $this->setLabels($importedRecord);
        $this->setEnumOptions($importedRecord);

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }

    /**
     * Set labels with locales mapping from settings.
     *
     * @param array $importedRecord
     */
    private function setLabels(array &$importedRecord)
    {
        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $this->getTransport()->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        $importedRecord['entity.label'] = $importedRecord['labels'][$defaultLocale] ?? $importedRecord['code'];
        $importedRecord['entity.label'] = mb_substr($importedRecord['entity.label'], 0, self::ENTITY_LABEL_MAX_LENGTH);
        $importedRecord['translatedLabels'] = [];

        foreach ($this->getTransport()->getAkeneoLocales() as $akeneoLocale) {
            $translation = $importedRecord['labels'][$akeneoLocale->getCode()] ?? null;
            if (!$translation) {
                continue;
            }

            $importedRecord['translatedLabels'][$akeneoLocale->getLocale()] = $translation;
        }
    }

    /**
     * Set enum options.
     *
     * @param array $importedRecord
     */
    private function setEnumOptions(array &$importedRecord)
    {
        if (empty($importedRecord['options']) || !is_array($importedRecord['options'])) {
            return;
        }

        $transport = $this->getTransport();
        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $transport->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        foreach ($importedRecord['options'] as $key => &$option) {
            $optionKey = sprintf('enum.enum_options.%d.id', $key);
            $importedRecord[$optionKey] = $option['code'];
            $optionKey = sprintf('enum.enum_options.%d.label', $key);
            $importedRecord[$optionKey] = $option['labels'][$defaultLocale] ?? $option['code'];
            $importedRecord['options'][$key]['translatedLabels'] = [];
            $importedRecord['options'][$key]['defaultLabel'] = $importedRecord[$optionKey];
            $optionKey = sprintf('enum.enum_options.%d.is_default', $key);
            $importedRecord[$optionKey] = '';

            foreach ($transport->getAkeneoLocales() as $akeneoLocale) {
                foreach ($this->getLocalizations($akeneoLocale->getLocale()) as $localization) {
                    if ($defaultLocalization->getLanguageCode() === $localization->getLanguageCode()) {
                        continue;
                    }

                    if (!isset($option['labels'][$akeneoLocale->getCode()])) {
                        continue;
                    }

                    $importedRecord['options'][$key]['translatedLabels'][$akeneoLocale->getLocale()] =
                        $option['labels'][$akeneoLocale->getCode()];
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaderConversionRules()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function getBackendHeader()
    {
        throw new \Exception('Normalization is not implemented!');
    }
}
