<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\ImportExport\AkeneoIntegrationTrait;
use Oro\Bundle\AkeneoBundle\Tools\AttributeFamilyCodeGenerator;
use Oro\Bundle\AkeneoBundle\Tools\Generator;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Generator\SlugGenerator;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\ImportExport\DataConverter\ProductDataConverter as BaseProductDataConverter;
use Oro\Bundle\ProductBundle\ProductVariant\Registry\ProductVariantFieldValueHandlerRegistry;
use Oro\Bundle\ProductBundle\Provider\ProductUnitsProvider;

/**
 * Converts data for imported row.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProductDataConverter extends BaseProductDataConverter implements ContextAwareInterface, ClosableInterface
{
    use AkeneoIntegrationTrait;
    use LocalizationAwareTrait;

    /** @var SlugGenerator */
    protected $slugGenerator;

    /** @var ConfigManager */
    protected $entityConfigManager;

    /** @var DateTimeFormatter */
    protected $dateTimeFormatter;

    /** @var string */
    protected $attachmentsDir;

    /** @var array */
    protected $akeneoFields = [];

    /** @var array */
    protected $systemFields = [];

    private $mappedAttributes = [];

    /** @var ProductUnitsProvider */
    protected $productUnitsProvider;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var ProductVariantFieldValueHandlerRegistry */
    private $productVariantFieldValueHandlerRegistry;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    public function setProductUnitsProvider(ProductUnitsProvider $productUnitsProvider): void
    {
        $this->productUnitsProvider = $productUnitsProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        unset($importedRecord['_links']);

        $this->processValues($importedRecord);
        $this->processSystemValues($importedRecord);
        unset($importedRecord['values']);

        $this->setPrimaryUnitPrecision($importedRecord);
        $this->setStatus($importedRecord);
        $this->setCategory($importedRecord);
        $this->setFamilyVariant($importedRecord);
        $this->setBrand($importedRecord);
        $this->setSku($importedRecord);

        $importedRecord = parent::convertToImportFormat($importedRecord, $skipNullValues);

        $this->setNames($importedRecord);
        $this->setSlugs($importedRecord);

        return $importedRecord;
    }

    private function setStatus(array &$importedRecord)
    {
        $importedRecord['status'] = empty($importedRecord['enabled']) ?
            Product::STATUS_DISABLED : Product::STATUS_ENABLED;

        if (!empty($importedRecord['family_variant'])) {
            $importedRecord['status'] = Product::STATUS_DISABLED;

            return;
        }

        if (!empty($importedRecord['parent'])) {
            $importedRecord['status'] = Product::STATUS_DISABLED;

            return;
        }
    }

    private function setPrimaryUnitPrecision(array &$importedRecord): void
    {
        $importedRecord['primaryUnitPrecision'] = $this->getPrimaryUnitPrecision($importedRecord);
    }

    private function setNames(array &$importedRecord): void
    {
        if (empty($importedRecord['names']['default'])) {
            $importedRecord['names']['default'] = [
                'fallback' => null,
                'string' => $importedRecord['sku'],
            ];
        }
    }

    /**
     * Set family variant for configurable products.
     */
    private function setFamilyVariant(array &$importedRecord)
    {
        $importedRecord['attributeFamily'] = ['code' => 'default_family'];
        if (!empty($importedRecord['family'])) {
            $importedRecord['attributeFamily'] = [
                'code' => AttributeFamilyCodeGenerator::generate($importedRecord['family']),
            ];
        }

        if (empty($importedRecord['family_variant'])) {
            return;
        }

        $importedRecord['type'] = Product::TYPE_CONFIGURABLE;
        $importedRecord['attributeFamily'] = [
            'code' => AttributeFamilyCodeGenerator::generate($importedRecord['family_variant']['family']),
        ];

        $sets = $importedRecord['family_variant']['variant_attribute_sets'] ?: [];
        $isTwoLevelFamilyVariant = count($sets) === 2;
        $isFirstLevelProduct = empty($importedRecord['parent']);
        $isSecondLevelProduct = !empty($importedRecord['parent']);

        if ($isTwoLevelFamilyVariant && $isSecondLevelProduct) {
            $sets = array_slice($sets, -1);
        }

        $variantFields = [];
        $this->prepareFieldMapping();

        foreach ($sets as $set) {
            foreach ($set['axes'] as $code) {
                if (array_key_exists($code, $this->akeneoFields)) {
                    $field = $this->akeneoFields[$code];

                    try {
                        $this->productVariantFieldValueHandlerRegistry->getVariantFieldValueHandler($field['type']);
                    } catch (\InvalidArgumentException $e) {
                        continue;
                    }

                    $variantFields[$field['name']] = $field['name'];
                }
            }
        }

        if (!$variantFields) {
            return;
        }

        $importedRecord['status'] = Product::STATUS_ENABLED;
        $importedRecord['variantFields'] = implode(',', $variantFields);

        if ($isTwoLevelFamilyVariant) {
            $allowSecondProductOnly = $this->getTransport()->getAkeneoVariantLevels() ===
                AkeneoSettings::TWO_LEVEL_FAMILY_VARIANT_SECOND_ONLY;
            if ($isFirstLevelProduct && $allowSecondProductOnly) {
                $importedRecord['status'] = Product::STATUS_DISABLED;
            }

            $allowFirstProductOnly = $this->getTransport()->getAkeneoVariantLevels() ===
                AkeneoSettings::TWO_LEVEL_FAMILY_VARIANT_FIRST_ONLY;
            if ($isSecondLevelProduct && $allowFirstProductOnly) {
                $importedRecord['status'] = Product::STATUS_DISABLED;
            }
        }
    }

    private function processValues(array &$importedRecord)
    {
        if (!is_array($importedRecord['values'])) {
            return;
        }

        $this->prepareFieldMapping();

        foreach ($importedRecord['values'] as $attributeCode => $value) {
            if (!array_key_exists($attributeCode, $this->akeneoFields)) {
                continue;
            }

            $field = $this->akeneoFields[$attributeCode];

            $this->processValue($importedRecord, $field, $value);
        }
    }

    private function processValue(array &$importedRecord, array $field, array $value)
    {
        $importExportProvider = $this->entityConfigManager->getProvider('importexport');
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

            return;
        }

        switch ($field['type']) {
            case 'enum':
                $importedRecord[$field['name']] = $this->processEnumType($value);
                break;
            case 'multiEnum':
                $importedRecord[$field['name']] = $this->processMultiEnumType($value);
                break;
            case 'file':
                $importedRecord[$field['name']] = $this->processFileType($value);
                break;
            case 'image':
                $importedRecord[$field['name']] = $this->processFileType($value);
                break;
            case 'multiFile':
                $importedRecord[$field['name']] = $this->processFileTypes($value);
                break;
            case 'multiImage':
                $importedRecord[$field['name']] = $this->processFileTypes($value);
                break;
            default:
                $importedRecord[$field['name']] = $this->processBasicType($value);
                break;
        }
    }

    private function processSystemValues(array &$importedRecord)
    {
        if (!is_array($importedRecord['values'])) {
            return;
        }

        $this->prepareFieldMapping();

        foreach ($importedRecord['values'] as $attributeCode => $value) {
            $systemFieldName = $this->getMappedAttribute($attributeCode);
            if (!$systemFieldName) {
                continue;
            }

            if (!array_key_exists($systemFieldName, $this->systemFields)) {
                continue;
            }

            $systemField = $this->systemFields[$systemFieldName];

            $this->processValue($importedRecord, $systemField, $value);
        }
    }

    private function getMappedAttribute(string $attributeCode): ?string
    {
        if (!$this->mappedAttributes) {
            $attributesMappings = trim(
                $this->getTransport()->getAkeneoAttributesMapping() ?? AkeneoSettings::DEFAULT_ATTRIBUTES_MAPPING,
                ';:'
            );

            if (!empty($attributesMappings)) {
                $attributesMapping = explode(';', $attributesMappings);
                foreach ($attributesMapping as $attributeMapping) {
                    list($akeneoAttribute, $systemAttribute) = explode(':', $attributeMapping);
                    if (!isset($akeneoAttribute, $systemAttribute)) {
                        continue;
                    }

                    $this->mappedAttributes[$systemAttribute] = $akeneoAttribute;
                }
            }
        }

        $key = array_search($attributeCode, $this->mappedAttributes);
        if ($key) {
            return $key;
        }

        return null;
    }

    private function prepareFieldMapping()
    {
        if ($this->systemFields) {
            return;
        }

        $fields = $this->fieldHelper->getFields(Product::class, true);
        $importExportProvider = $this->entityConfigManager->getProvider('importexport');

        foreach ($fields as $field) {
            if (!$this->entityConfigManager->hasConfig(Product::class, $field['name'])) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);
            if ('akeneo' === $importExportConfig->get('source')) {
                $this->akeneoFields[$importExportConfig->get('source_name')] = $field;

                continue;
            }

            $this->systemFields[mb_strtolower($field['name'])] = $field;
        }
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

    private function processFileType(array $value): array
    {
        $item = array_shift($value);

        return ['uri' => $this->getAttachmentPath($item['data'])];
    }

    private function processFileTypes(array $value): array
    {
        $items = array_shift($value);

        $paths = [];
        foreach ($items['data'] as $item) {
            $paths[] = ['uri' => $this->getAttachmentPath($item)];
        }

        return $paths;
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
            $value = sprintf(
                '%s %s',
                (float)$item['data']['amount'],
                ucfirst(mb_strtolower($item['data']['unit']))
            );

            if (isset($item['data']['symbol'])) {
                $value = sprintf(
                    '%s (%s)',
                    $value,
                    $item['data']['symbol']
                );
            }

            $item['data'] = $value;
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

    private function setBrand(array &$importedRecord)
    {
        if (!empty($importedRecord['brand'])) {
            $importedRecord['brand'] = ['akeneo_code' => $importedRecord['brand']];
        }
    }

    private function setSku(array &$importedRecord)
    {
        $identifier = $importedRecord['identifier'] ?? $importedRecord['code'];

        $importedRecord['sku'] = (string)($importedRecord['sku'] ?? $identifier);
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
        $this->akeneoFields = [];
        $this->systemFields = [];
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
            'precision' => $precision,
            'sell' => true,
        ];
    }

    public function setProductVariantFieldValueHandlerRegistry(
        ProductVariantFieldValueHandlerRegistry $productVariantFieldValueHandlerRegistry
    ): void {
        $this->productVariantFieldValueHandlerRegistry = $productVariantFieldValueHandlerRegistry;
    }
}
