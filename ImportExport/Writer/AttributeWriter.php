<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\AkeneoBundle\Config\ChangesAwareInterface;
use Oro\Bundle\AkeneoBundle\Tools\EnumSynchronizer;
use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\AttributeTypeRegistry;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\ImportExport\Writer\AttributeWriter as BaseAttributeWriter;
use Oro\Bundle\EntityExtendBundle\Entity\EnumValueTranslation;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\TranslationBundle\Entity\Translation;
use Oro\Bundle\TranslationBundle\Manager\TranslationManager;

/**
 * Import writer for product attributes.
 */
class AttributeWriter extends BaseAttributeWriter
{
    const ATTRIBUTE_LABELS_CONTEXT_KEY = 'attributeLabels';

    /** @var TranslationManager */
    private $translationManager;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var AttributeTypeRegistry */
    private $attributeTypeRegistry;

    /** @var CacheProvider */
    private $cacheProvider;

    /** @var array */
    private $attributeLabels = [];

    /** @var array */
    private $optionLabels = [];

    /** @var array */
    private $fieldNameMapping = [];

    /** @var array */
    private $fieldTypeMapping = [];

    public function initialize()
    {
        $this->attributeLabels = [];
        $this->optionLabels = [];
        $this->fieldNameMapping = [];
        $this->fieldTypeMapping = [];
    }

    public function flush()
    {
        $this->cacheProvider->delete('attribute_attributeLabels');
        $this->cacheProvider->delete('attribute_optionLabels');
        $this->cacheProvider->delete('attribute_fieldNameMapping');
        $this->cacheProvider->delete('attribute_fieldTypeMapping');
        $this->attributeLabels = null;
        $this->optionLabels = null;
        $this->fieldNameMapping = null;
        $this->fieldTypeMapping = null;
    }

    public function setEnumSynchronizer(EnumSynchronizer $enumSynchronizer): void
    {
        $this->enumSynchronizer = $enumSynchronizer;
    }

    public function setAttributeTypeRegistry(AttributeTypeRegistry $attributeTypeRegistry): void
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    public function setTranslationManager(TranslationManager $translationManager)
    {
        $this->translationManager = $translationManager;
    }

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    public function setCacheProvider(CacheProvider $cacheProvider): void
    {
        $this->cacheProvider = $cacheProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $translations = [];

        foreach ($items as $item) {
            $translations = array_merge($translations, $this->writeItem($item));
        }

        if ($this->configManager instanceof ChangesAwareInterface && $this->configManager->hasChanges()) {
            $this->configManager->flush();
        }

        $this->translationHelper->saveTranslations($translations);
        $this->saveAttributeTranslationsFromContext($items);
        $this->saveOptionTranslationsFromContext($items);
    }

    /**
     * Save attribute translations from context.
     */
    private function saveAttributeTranslationsFromContext(array $items)
    {
        $provider = $this->configManager->getProvider('entity');
        $this->attributeLabels = $this->cacheProvider->fetch('attribute_attributeLabels') ?? [];

        foreach ($items as $item) {
            $className = $item->getEntity()->getClassName();
            $fieldName = $item->getFieldName();

            if (!isset($this->attributeLabels[$fieldName])) {
                continue;
            }

            $config = $provider->getConfig($className, $fieldName);
            $labelKey = $config->get('label');

            foreach ($this->attributeLabels[$fieldName] as $locale => $value) {
                $this->translationManager->saveTranslation(
                    $labelKey,
                    $value,
                    $locale,
                    TranslationManager::DEFAULT_DOMAIN,
                    Translation::SCOPE_UI
                );
            }
        }

        $this->translationManager->flush();
    }

    /**
     * Save option translations from context.
     */
    private function saveOptionTranslationsFromContext(array $items)
    {
        $provider = $this->configManager->getProvider('enum');
        $this->optionLabels = $this->cacheProvider->fetch('attribute_optionLabels') ?? [];

        foreach ($items as $item) {
            $className = $item->getEntity()->getClassName();
            $fieldName = $item->getFieldName();

            if (!isset($this->optionLabels[$fieldName])) {
                continue;
            }

            $enumCode = $provider->getConfig($className, $fieldName)->get('enum_code');

            if (!$enumCode) {
                continue;
            }

            $enumValueClassName = ExtendHelper::buildEnumValueClassName($enumCode);
            $manager = $this->doctrineHelper->getEntityManager($enumValueClassName);

            foreach ($this->enumSynchronizer->getEnumOptions($enumValueClassName) as $option) {
                foreach ($this->optionLabels[$fieldName] as $label) {
                    if ($label['default'] !== $option['label'] || false === is_array($label['translations'])) {
                        continue;
                    }

                    foreach ($label['translations'] as $locale => $value) {
                        $translation = new EnumValueTranslation();
                        $translation->setLocale($locale)
                            ->setObjectClass($enumValueClassName)
                            ->setContent($value)
                            ->setForeignKey($option['id'])
                            ->setField('name');
                        $manager->persist($translation);
                    }
                }
            }

            $manager->flush();
            $manager->clear();
        }
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function setAttributeData(FieldConfigModel $fieldConfigModel)
    {
        parent::setAttributeData($fieldConfigModel);

        $extendProvider = $this->configManager->getProvider('extend');
        $importExportProvider = $this->configManager->getProvider('importexport');

        if (!$extendProvider || !$importExportProvider) {
            return;
        }

        $className = $fieldConfigModel->getEntity()->getClassName();
        $fieldName = $fieldConfigModel->getFieldName();

        $importExportConfig = $importExportProvider->getConfig($className, $fieldName);
        $importExportConfig->set('source', 'akeneo');

        if ('file' === $fieldConfigModel->getType()) {
            $importExportConfig->set('full', false);
            $attachmentProvider = $this->configManager->getProvider('attachment');
            $attachmentConfig = $attachmentProvider->getConfig($className, $fieldName);
            $attachmentConfig->set('mimetypes', implode("\n", ['application/*', 'image/*', 'text/*']));
            $this->configManager->persist($attachmentConfig);
        }

        $this->fieldNameMapping = $this->cacheProvider->fetch('attribute_fieldNameMapping') ?? [];
        $sourceName = $this->fieldNameMapping[$fieldName] ?? null;
        if (!$sourceName) {
            throw new \InvalidArgumentException(
                sprintf('Unknown source name for "%s::%s"', $className, $fieldName)
            );
        }
        $importExportConfig->set('source_name', $sourceName);
        $this->configManager->persist($importExportConfig);

        $attributeProvider = $this->configManager->getProvider('attribute');
        $searchProvider = $this->configManager->getProvider('search');
        $attributeConfig = $attributeProvider->getConfig($className, $fieldName);
        $searchConfig = $searchProvider->getConfig($className, $fieldName);
        $type = $this->attributeTypeRegistry->getAttributeType($fieldConfigModel);
        $extendConfig = $extendProvider->getConfig($className, $fieldName);

        $this->fieldTypeMapping = $this->cacheProvider->fetch('attribute_fieldTypeMapping') ?? [];
        $importedFieldType = $this->fieldTypeMapping[$fieldConfigModel->getFieldName()] ?? null;

        if ($extendConfig->is('state', ExtendScope::STATE_NEW)) {
            $this->saveDatagridConfig($className, $fieldName);
            $this->setSearchConfig($searchConfig, $importedFieldType);
        }

        $attributeConfig->set('is_attribute', true);

        if (false === $type->isSearchable()) {
            $attributeConfig->set('searchable', false);
            $searchConfig->set('searchable', false);
        }
        if (false === $type->isFilterable()) {
            $attributeConfig->set('filterable', false);
        }

        $this->configManager->persist($attributeConfig);
        $this->configManager->persist($searchConfig);

        if (RelationType::MANY_TO_MANY !== $fieldConfigModel->getType()) {
            return;
        }

        $extendConfig->set('target_entity', LocalizedFallbackValue::class);
        $extendConfig->set('bidirectional', false);
        $extendConfig->set('without_default', true);
        $extendConfig->set('cascade', ['all']);

        $relationKey = ExtendHelper::buildRelationKey(
            Product::class,
            $fieldConfigModel->getFieldName(),
            RelationType::MANY_TO_MANY,
            LocalizedFallbackValue::class
        );

        $extendConfig->set('relation_key', $relationKey);

        $fieldType = $importedFieldType === 'pim_catalog_text' ? 'string' : 'text';

        $extendConfig->set('target_title', [$fieldType]);
        $extendConfig->set('target_detailed', [$fieldType]);
        $extendConfig->set('target_grid', [$fieldType]);

        $importExportConfig->set('fallback_field', $fieldType);
        $this->configManager->persist($extendConfig);
        $this->configManager->persist($importExportConfig);
    }

    private function saveDatagridConfig(string $className, string $fieldName): void
    {
        $datagridProvider = $this->configManager->getProvider('datagrid');
        $datagridConfig = $datagridProvider->getConfig($className, $fieldName);
        $datagridConfig->set('is_visible', DatagridScope::IS_VISIBLE_FALSE);

        $this->configManager->persist($datagridConfig);
    }

    private function setSearchConfig(ConfigInterface $searchConfig, ?string $importedFieldType): void
    {
        $searchable = !in_array($importedFieldType, ['pim_catalog_file', 'pim_catalog_date']);
        $searchConfig->set('searchable', $searchable);
    }
}
