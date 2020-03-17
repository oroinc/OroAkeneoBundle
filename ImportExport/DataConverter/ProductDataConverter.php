<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Doctrine\Common\Util\Inflector;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Tools\AttributeFamilyCodeGenerator;
use Oro\Bundle\AkeneoBundle\Tools\AttributeTypeConverter;
use Oro\Bundle\AkeneoBundle\Tools\Generator;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Generator\SlugGenerator;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\ImportExport\DataConverter\ProductDataConverter as BaseProductDataConverter;
use Oro\Bundle\ProductBundle\Provider\ProductUnitsProvider;

/**
 * Converts data for imported row.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProductDataConverter extends BaseProductDataConverter implements ContextAwareInterface, ClosableInterface
{
    use AkeneoIntegrationTrait;

    /**
     * @var ContextInterface
     */
    protected $context;

    /**
     * @var SlugGenerator
     */
    protected $slugGenerator;

    /**
     * @var ConfigManager
     */
    protected $entityConfigManager;

    /**
     * @var DateTimeFormatter
     */
    protected $dateTimeFormatter;

    /**
     * @var string
     */
    protected $attachmentsDir;

    /** @var array */
    protected $fieldMapping = [];

    /** @var ProductUnitsProvider */
    protected $productUnitsProvider;

    /** @var string */
    private $codePrefix;

    public function setProductUnitsProvider(ProductUnitsProvider $productUnitsProvider): void
    {
        $this->productUnitsProvider = $productUnitsProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function setImportExportContext(ContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        unset($importedRecord['_links']);

        $importedRecord['sku'] = $importedRecord['identifier'] ?? $importedRecord['code'];
        $importedRecord['primaryUnitPrecision'] = $this->getPrimaryUnitPrecision($importedRecord);

        if (!empty($importedRecord['family'])) {
            $importedRecord['attributeFamily'] = [
                'code' => AttributeFamilyCodeGenerator::generate($importedRecord['family'], $this->codePrefix),
            ];
        }

        $importedRecord['inventory_status'] = ['id' => Product::INVENTORY_STATUS_IN_STOCK];
        $importedRecord['type'] = 'simple';
        $importedRecord['status'] = Product::STATUS_ENABLED;
        if (array_key_exists('enabled', $importedRecord)) {
            $importedRecord['status'] = empty($importedRecord['enabled']) ?
                Product::STATUS_DISABLED : Product::STATUS_ENABLED;
        }

        $this->processValues($importedRecord);
        $this->setSlugs($importedRecord);
        $this->setCategory($importedRecord);
        $this->setFamilyVariant($importedRecord);

        $importedRecord = parent::convertToImportFormat($importedRecord, $skipNullValues);

        if (!isset($importedRecord['names'])) {
            $importedRecord['names'] = [
                'default' => [
                    'fallback' => null,
                    'string' => $importedRecord['sku'],
                ],
            ];
        }

        return $importedRecord;
    }

    /**
     * Set family variant for configurable products.
     */
    private function setFamilyVariant(array &$importedRecord)
    {
        if (empty($importedRecord['family_variant'])) {
            $importedRecord['type'] = 'simple';

            return;
        }

        $importedRecord['type'] = 'configurable';
        $importedRecord['attributeFamily'] = [
            'code' => AttributeFamilyCodeGenerator::generate($importedRecord['family_variant']['family'], $this->codePrefix),
        ];

        $variantFields = [];
        $fieldMapping = $this->getFieldMapping();

        foreach ($importedRecord['family_variant']['variant_attribute_sets'] as $set) {
            foreach ($set['axes'] as $code) {
                if (array_key_exists($code, $fieldMapping)) {
                    $field = $fieldMapping[$code];

                    $variantFields[] = $field['name'];
                }
            }
        }

        if (count($variantFields) > 0) {
            $importedRecord['variantFields'] = implode(',', $variantFields);
        }
    }

    /**
     * Convert product values.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function processValues(array &$importedRecord)
    {
        if (false === is_array($importedRecord['values'])) {
            return;
        }

        $importExportProvider = $this->entityConfigManager->getProvider('importexport');

        $fieldMapping = $this->getFieldMapping();

        foreach ($importedRecord['values'] as $attributeCode => $value) {
            $field = $this->getField($attributeCode, $fieldMapping);

            if (!$field) {
                unset($importedRecord['values'][$attributeCode]);

                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);

            $isLocalizable = in_array($field['type'], [RelationType::MANY_TO_MANY, RelationType::TO_MANY]) &&
                LocalizedFallbackValue::class === $field['related_entity_name'];

            if ($isLocalizable) {
                $importedRecord[$field['name']] = $this->processRelationType(
                    $value,
                    $importExportConfig->get('fallback_field', false, 'text'),
                    $this->getDefaultLocalization(),
                    $this->getTransport()
                );

                unset($importedRecord['values'][$attributeCode]);

                continue;
            }

            $valueFirstItem = array_values($value)[0];

            if (AttributeTypeConverter::convert($valueFirstItem['type']) !== $field['type']) {
                unset($importedRecord['values'][$attributeCode]);

                continue;
            }

            switch ($field['type']) {
                case 'enum':
                    $importedRecord[$field['name']] = $this->processEnumType($value);
                    break;
                case 'multiEnum':
                    $importedRecord[$field['name']] = $this->processMultiEnumType($value);
                    break;
                case 'file':
                    if ($valueFirstItem['data']) {
                        $importedRecord[$field['name']] = $this->processFileType($value);
                    }
                    break;
                default:
                    $importedRecord[$field['name']] = $this->processBasicType($value);
                    break;
            }

            unset($importedRecord['values'][$attributeCode]);
        }
    }

    /**
     * Gets field by attribute code.
     */
    private function getField(string $attributeCode, array $fieldMapping): ?array
    {
        $attributeCodes = [
            $attributeCode,
            Inflector::singularize(Inflector::camelize($attributeCode)),
            Inflector::pluralize(Inflector::camelize($attributeCode)),
        ];

        foreach ($attributeCodes as $guessedCode) {
            if (!empty($fieldMapping[$guessedCode])) {
                $field = $fieldMapping[$guessedCode];
            }
        }

        return $field ?? null;
    }

    private function getFieldMapping()
    {
        if ($this->fieldMapping) {
            return $this->fieldMapping;
        }

        $fields = $this->fieldHelper->getFields(Product::class, true);
        $extendProvider = $this->entityConfigManager->getProvider('extend');
        $importExportProvider = $this->entityConfigManager->getProvider('importexport');

        foreach ($fields as $field) {
            if (false === $this->entityConfigManager->hasConfig(Product::class, $field['name'])) {
                continue;
            }

            $extendConfig = $extendProvider->getConfig(Product::class, $field['name']);

            if (ExtendScope::STATE_ACTIVE !== $extendConfig->get('state')) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);
            if ('akeneo' !== $importExportConfig->get('source')) {
                $this->fieldMapping[$field['name']] = $field;

                continue;
            }

            $source = $importExportConfig->get('source_name');
            if (!$source) {
                continue;
            }

            $this->fieldMapping[$source] = $field;
        }

        return $this->fieldMapping;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function processRelationType(
        array $value,
        string $fallbackField,
        Localization $defaultLocalization,
        AkeneoSettings $transport
    ): array {
        $result = [];

        foreach ($value as $item) {
            if ('pim_catalog_date' === $item['type']) {
                $dateTime = new \DateTime($item['data']);
                $item['data'] = $this->dateTimeFormatter->format($dateTime);
            }
            if (null === $item['locale']) {
                $result['default'] = [
                    'fallback' => null,
                    $fallbackField => html_entity_decode($item['data']),
                ];

                continue;
            }

            foreach ($transport->getAkeneoLocales() as $akeneoLocale) {
                if ($akeneoLocale->getCode() !== $item['locale']) {
                    continue;
                }

                if ('pim_catalog_date' === $item['type']) {
                    $dateTime = new \DateTime($item['data']);
                    $item['data'] = $this->dateTimeFormatter->format(
                        $dateTime,
                        null,
                        \IntlDateFormatter::NONE,
                        $item['locale']
                    );
                }

                foreach ($this->getLocalizations($akeneoLocale->getLocale()) as $localization) {
                    $result[$localization->getName()] = [
                        'fallback' => null,
                        $fallbackField => html_entity_decode($item['data']),
                    ];
                }
            }
        }

        if (false === isset($result['default'])) {
            if (isset($result[$defaultLocalization->getName()])) {
                $result['default'] = $result[$defaultLocalization->getName()];
            } elseif (count($result) > 1) {
                $result['default'] = array_values($result)[0];
            }
        }

        return $result;
    }

    private function processEnumType(array $value): array
    {
        $item = array_shift($value);

        return [
            'id' => $this->prepareEnumId($item['data']),
        ];
    }

    private function processMultiEnumType(array $value): array
    {
        $ids = [];
        $result = [];

        foreach ($value as $item) {
            $ids = array_merge($ids, $item['data']);
        }

        foreach (array_unique($ids) as $data) {
            $result[] = [
                'id' => $this->prepareEnumId($data),
            ];
        }

        return $result;
    }

    /**
     * Prepares enum id like saved already attribute code.
     */
    private function prepareEnumId(?string $id): ?string
    {
        return $id !== null ? Generator::generateLabel($id) : null;
    }

    private function processFileType(array $value): string
    {
        $item = array_shift($value);

        return $this->getAttachmentPath($item['data']);
    }

    protected function getAttachmentPath(string $code): string
    {
        return sprintf('%s/%s', rtrim($this->attachmentsDir, '/'), $code);
    }

    /**
     * @return mixed
     */
    private function processBasicType(array $value)
    {
        $item = array_shift($value);

        if ('pim_catalog_metric' === $item['type']) {
            $item['data'] = sprintf('%s %s', $item['data']['amount'], $item['data']['unit']);
        }

        return $item['data'];
    }

    /**
     * Sets slugs generated from names.
     */
    private function setSlugs(array &$importedRecord)
    {
        $importedRecord['slugPrototypes'] = $importedRecord['names'] ?? [];
        foreach ($importedRecord['slugPrototypes'] as &$slugPrototype) {
            $slugPrototype['string'] = $this->slugGenerator->slugify($slugPrototype['string']);
        }
    }

    /**
     * Set category.
     */
    private function setCategory(array &$importedRecord)
    {
        $categories = array_filter((array)$importedRecord['categories'] ?? []);
        unset($importedRecord['categories']);
        if (!$categories) {
            return;
        }

        $importedRecord['category:akeneo_code'] = reset($categories);
        $importedRecord['category:channel:id'] = $this->context->getOption('channel');
    }

    public function setEntityConfigManager(ConfigManager $entityConfigManager): void
    {
        $this->entityConfigManager = $entityConfigManager;
    }

    public function setSlugGenerator(SlugGenerator $slugGenerator)
    {
        $this->slugGenerator = $slugGenerator;
    }

    public function setDateTimeFormatter(DateTimeFormatter $dateTimeFormatter): void
    {
        $this->dateTimeFormatter = $dateTimeFormatter;
    }

    public function setAttachmentsDir(string $attachmentsDir): void
    {
        $this->attachmentsDir = $attachmentsDir;
    }

    public function close()
    {
        $this->fieldMapping = [];
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

    protected function getPrimaryUnitPrecision(array $importedRecord): array
    {
        $unit = $this->configManager->get('oro_product.default_unit');
        $precision = $this->configManager->get('oro_product.default_unit_precision');

        $unitAttribute = $this->getTransport()->getProductUnitAttribute();
        $unitPrecisionAttribute = $this->getTransport()->getProductUnitPrecisionAttribute();

        $availableUnits = $this->productUnitsProvider->getAvailableProductUnits();

        if (isset($importedRecord['values'][$unitAttribute])) {
            $unitData = reset($importedRecord['values'][$unitAttribute]);
            if (isset($unitData['data']) && in_array($unitData['data'], $availableUnits)) {
                $unit = $unitData['data'];
            }
        }
        if (isset($importedRecord['values'][$unitPrecisionAttribute])) {
            $unitPrecisionData = reset($importedRecord['values'][$unitPrecisionAttribute]);
            if (isset($unitPrecisionData['data'])) {
                $precision = (int)$unitPrecisionData['data'];
            }
        }

        return [
            'unit' => ['code' => $unit],
            'precision' =>  $precision,
            'sell' => true,
        ];
    }

    public function setCodePrefix(string $codePrefix): void
    {
        $this->codePrefix = $codePrefix;
    }
}
