<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\AkeneoBundle\Tools\AttributeFamilyCodeGenerator;
use Oro\Bundle\AkeneoBundle\Tools\FieldConfigModelFieldNameGenerator;
use Oro\Bundle\AkeneoBundle\Tools\Generator;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager as EntityConfigManager;
use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\LocaleBundle\ImportExport\DataConverter\LocalizedFallbackValueAwareDataConverter;
use Oro\Bundle\ProductBundle\Entity\Product;

class AttributeFamilyDataConverter extends LocalizedFallbackValueAwareDataConverter implements ContextAwareInterface
{
    use AkeneoIntegrationTrait;

    /** @var array */
    protected $fieldMapping = [];

    /**
     * @var EntityConfigManager
     */
    protected $entityConfigManager;

    /** @var string */
    private $codePrefix;

    /**
     * @param EntityConfigManager $entityConfigManager
     */
    public function setEntityConfigManager(EntityConfigManager $entityConfigManager)
    {
        $this->entityConfigManager = $entityConfigManager;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $importedRecord['code'] = AttributeFamilyCodeGenerator::generate($importedRecord['code'], $this->codePrefix);
        $importedRecord['entityClass'] = Product::class;
        $importedRecord['isEnabled'] = true;
        $importedRecord['channel:id'] = $this->getContext()->getOption('channel');

        $this->setLabels($importedRecord);

        foreach ($importedRecord['groups'] as $key => &$group) {
            $this->setLabels($group);
            $group['akeneo_code'] = $group['code'];

            foreach ($group['attributes'] as $attributeCode) {
                $entityConfigFieldId = $this->entityConfigManager->getConfigModelId(
                    $importedRecord['entityClass'],
                    FieldConfigModelFieldNameGenerator::generate($attributeCode, $this->codePrefix)
                );

                if ($entityConfigFieldId) {
                    $group['attributeRelations'][] = ['entityConfigFieldId' => $entityConfigFieldId];

                    continue;
                }

                // @BC: Keep Akeneo_Aken_1706289854
                $fieldName = $this->getFieldMapping()[$attributeCode] ?? null;
                if (!$fieldName) {
                    continue;
                }
                $entityConfigFieldId = $this->entityConfigManager->getConfigModelId(
                    $importedRecord['entityClass'],
                    $fieldName
                );
                if ($entityConfigFieldId) {
                    $group['attributeRelations'][] = ['entityConfigFieldId' => $entityConfigFieldId];

                    continue;
                }
            }
        }

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }

    private function getFieldMapping(): array
    {
        if ($this->fieldMapping) {
            return $this->fieldMapping;
        }

        $importExportProvider = $this->entityConfigManager->getProvider('importexport');
        foreach ($importExportProvider->getConfigs(Product::class) as $field) {
            if ('akeneo' !== $field->get('source')) {
                continue;
            }

            $source = $field->get('source_name');
            if (!$source) {
                continue;
            }

            $this->fieldMapping[$source] = $field->getId()->getFieldName();
        }

        return $this->fieldMapping;
    }

    /**
     * Set labels with locales mapping from settings.
     */
    private function setLabels(array &$importedRecord)
    {
        $labels = $importedRecord['labels'];

        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $this->getTransport()->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        $importedRecord['labels'] = [
            'default' => [
                'fallback' => null,
                'string' => $labels[$defaultLocale] ?? Generator::generateLabel($importedRecord['code']),
            ],
        ];

        $akeneoLocales = $this->getTransport()->getAkeneoLocales();
        foreach ($akeneoLocales as $akeneoLocale) {
            foreach ($this->getLocalizations($akeneoLocale->getLocale()) as $localization) {
                if (!$localization || $defaultLocalization->getLanguageCode() === $localization->getLanguageCode()) {
                    continue;
                }

                $importedRecord['labels'][$localization->getName()] = [
                    'fallback' => null,
                    'string' => $labels[$akeneoLocale->getCode()] ?? null,
                ];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaderConversionRules()
    {
        return [
            'labels' => 'labels',
            'code' => 'code',
            'groups' => 'attributeGroups',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getBackendHeader()
    {
        throw new \Exception('Normalization is not implemented!');
    }

    public function setCodePrefix(string $codePrefix): void
    {
        $this->codePrefix = $codePrefix;
    }
}
