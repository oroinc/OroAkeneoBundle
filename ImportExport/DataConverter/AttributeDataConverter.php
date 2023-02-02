<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Oro\Bundle\AkeneoBundle\ImportExport\AkeneoIntegrationTrait;
use Oro\Bundle\AkeneoBundle\Tools\AttributeTypeConverter;
use Oro\Bundle\AkeneoBundle\Tools\FieldConfigModelFieldNameGenerator;
use Oro\Bundle\AkeneoBundle\Tools\Generator;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\ImportExport\DataConverter\EntityFieldDataConverter;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Converts data to import format.
 */
class AttributeDataConverter extends EntityFieldDataConverter
{
    use AkeneoIntegrationTrait;
    use LocalizationAwareTrait;

    private const ENTITY_LABEL_MAX_LENGTH = 50;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var TranslatorInterface */
    protected $translator;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $type = AttributeTypeConverter::convert($importedRecord['type']);
        if (!$type) {
            $message = sprintf(
                'Attribute "%s" type "%s" is not supported',
                $importedRecord['code'],
                $importedRecord['type']
            );

            if ($this->translator instanceof TranslatorInterface) {
                $this->context->addError(
                    $this->translator->trans(
                        'oro.akeneo.error',
                        [
                            '%error%' => $message,
                            '%item%' => json_encode(
                                $importedRecord,
                                \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                            ),
                        ]
                    )
                );
            }

            throw new InvalidItemException($message, $importedRecord);
        }

        $importedRecord['type'] = $type;
        $importedRecord['fieldName'] = FieldConfigModelFieldNameGenerator::generate($importedRecord['code']);
        $importedRecord['entity:id'] = (int)$this->context->getValue('entity_id');
        $this->setLabels($importedRecord);
        $this->setEnumOptions($importedRecord);

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }

    /**
     * Set labels with locales mapping from settings.
     */
    private function setLabels(array &$importedRecord)
    {
        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $this->getTransport()->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        $importedRecord['entity.label'] = $importedRecord['labels'][$defaultLocale] ??
            Generator::generateLabel($importedRecord['code']);

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
     */
    private function setEnumOptions(array &$importedRecord): void
    {
        if (empty($importedRecord['options']) || !is_array($importedRecord['options'])) {
            return;
        }

        $transport = $this->getTransport();
        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $transport->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        foreach ($importedRecord['options'] as $key => &$option) {
            $optionKey = sprintf('enum.enum_options.%d.priority', $key);
            $importedRecord[$optionKey] = $option['sort_order'];
            $optionKey = sprintf('enum.enum_options.%d.id', $key);
            $importedRecord[$optionKey] = Generator::generateLabel($option['code']);
            $optionKey = sprintf('enum.enum_options.%d.label', $key);
            $importedRecord[$optionKey] =
                $option['labels'][$defaultLocale] ?? Generator::generateLabel($option['code']);
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
