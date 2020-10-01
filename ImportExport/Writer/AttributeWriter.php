<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\AkeneoBundle\Config\ChangesAwareInterface;
use Oro\Bundle\AkeneoBundle\Tools\EnumSynchronizer;
use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\AttributeTypeRegistry;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\ImportExport\Writer\AttributeWriter as BaseAttributeWriter;
use Oro\Bundle\EntityExtendBundle\Entity\EnumValueTranslation;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\TranslationBundle\Entity\Translation;
use Oro\Bundle\TranslationBundle\Manager\TranslationManager;

/**
 * Import writer for product attributes.
 */
class AttributeWriter extends BaseAttributeWriter implements StepExecutionAwareInterface
{
    const ATTRIBUTE_LABELS_CONTEXT_KEY = 'attributeLabels';
    const MAX_SIZE = 10;
    const MAX_WIDTH = 100;
    const MAX_HEIGHT = 100;

    /** @var TranslationManager */
    private $translationManager;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var AttributeTypeRegistry */
    private $attributeTypeRegistry;

    /** @var StepExecution */
    private $stepExecution;

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

    /** @var int */
    private $organizationId;

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

        $this->organizationId = null;
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

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
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
                        $translation = $this->doctrineHelper
                            ->getEntityRepository(EnumValueTranslation::class)
                            ->findOneBy(
                                [
                                    'locale' => $locale,
                                    'objectClass' => $enumValueClassName,
                                    'foreignKey' => $option['id'],
                                    'field' => 'name',
                                ]
                            );
                        if (!$translation) {
                            $translation = new EnumValueTranslation();
                            $translation
                                ->setLocale($locale)
                                ->setObjectClass($enumValueClassName)
                                ->setForeignKey($option['id'])
                                ->setField('name');
                            $manager->persist($translation);
                        }
                        $translation->setContent($value);
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
        $extendProvider = $this->configManager->getProvider('extend');
        $importExportProvider = $this->configManager->getProvider('importexport');

        if (!$extendProvider || !$importExportProvider) {
            return;
        }

        $className = $fieldConfigModel->getEntity()->getClassName();
        $fieldName = $fieldConfigModel->getFieldName();

        $this->fieldNameMapping = $this->cacheProvider->fetch('attribute_fieldNameMapping') ?? [];
        $sourceName = $this->fieldNameMapping[$fieldName] ?? null;
        if (!$sourceName) {
            throw new \InvalidArgumentException(sprintf('Unknown source name for "%s::%s"', $className, $fieldName));
        }

        $importExportConfig = $importExportProvider->getConfig($className, $fieldName);
        $importExportConfig->set('source', 'akeneo');
        $importExportConfig->set('source_name', $sourceName);
        $this->configManager->persist($importExportConfig);

        $attributeProvider = $this->configManager->getProvider('attribute');
        $attributeConfig = $attributeProvider->getConfig($className, $fieldName);

        $extendConfig = $extendProvider->getConfig($className, $fieldName);
        if ($extendConfig->is('state', ExtendScope::STATE_NEW)) {
            $type = $this->attributeTypeRegistry->getAttributeType($fieldConfigModel);

            $this->saveDatagridConfig($className, $fieldName);
            $this->saveViewConfig($className, $fieldName);
            $this->saveFormConfig($className, $fieldName);
            $this->saveAttachmentConfig($className, $fieldName, $fieldConfigModel->getType());
            $this->saveSearchConfig($className, $fieldName, $type->isSearchable($fieldConfigModel));

            $attributeConfig->set('searchable', $type->isSearchable($fieldConfigModel));
            $attributeConfig->set('filterable', $type->isFilterable($fieldConfigModel));
            $attributeConfig->set('sortable', $type->isSortable($fieldConfigModel));
            $attributeConfig->set('visible', false);
            $attributeConfig->set('enabled', false);
        }

        $attributeConfig->set('is_attribute', true);
        $attributeConfig->set('is_global', false);
        $attributeConfig->set('organization_id', $this->getOrganizationId());
        $this->configManager->persist($attributeConfig);

        parent::setAttributeData($fieldConfigModel);

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

        $this->fieldTypeMapping = $this->cacheProvider->fetch('attribute_fieldTypeMapping') ?? [];
        $importedFieldType = $this->fieldTypeMapping[$fieldConfigModel->getFieldName()] ?? null;
        $fieldType = $importedFieldType === 'pim_catalog_text' ? 'string' : 'text';

        $extendConfig->set('target_title', [$fieldType]);
        $extendConfig->set('target_detailed', [$fieldType]);
        $extendConfig->set('target_grid', [$fieldType]);

        $importExportConfig->set('fallback_field', $fieldType);
        $this->configManager->persist($extendConfig);
        $this->configManager->persist($importExportConfig);
    }

    private function getOrganizationId(): ?int
    {
        if (!$this->organizationId) {
            $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');
            if (!$channelId) {
                return null;
            }

            /** @var Channel $channel */
            $channel = $this->doctrineHelper->getEntity(Channel::class, $channelId);
            if (!$channel) {
                return null;
            }

            $this->organizationId = $channel->getOrganization()->getId();
        }

        return $this->organizationId;
    }

    private function saveDatagridConfig(string $className, string $fieldName): void
    {
        $datagridProvider = $this->configManager->getProvider('datagrid');
        $datagridConfig = $datagridProvider->getConfig($className, $fieldName);
        $datagridConfig->set('is_visible', DatagridScope::IS_VISIBLE_FALSE);

        $this->configManager->persist($datagridConfig);
    }

    private function saveFormConfig(string $className, string $fieldName): void
    {
        $provider = $this->configManager->getProvider('form');
        $config = $provider->getConfig($className, $fieldName);
        $config->set('is_enabled', false);

        $this->configManager->persist($config);
    }

    private function saveViewConfig(string $className, string $fieldName): void
    {
        $provider = $this->configManager->getProvider('view');
        $config = $provider->getConfig($className, $fieldName);
        $config->set('is_displayable', false);

        $this->configManager->persist($config);
    }

    private function saveAttachmentConfig(string $className, string $fieldName, string $type): void
    {
        if (!in_array($type, ['image', 'file', 'multiFile', 'multiImage'], true)) {
            return;
        }

        $attachmentProvider = $this->configManager->getProvider('attachment');
        $attachmentConfig = $attachmentProvider->getConfig($className, $fieldName);
        $attachmentConfig->set('file_applications', ['default', 'commerce']);
        $attachmentConfig->set('acl_protected', true);
        $attachmentConfig->set('maxsize', self::MAX_SIZE);
        $attachmentConfig->set('width', self::MAX_WIDTH);
        $attachmentConfig->set('height', self::MAX_HEIGHT);

        $this->configManager->persist($attachmentConfig);
    }

    private function saveSearchConfig(string $className, string $fieldName, bool $searchable): void
    {
        $searchProvider = $this->configManager->getProvider('search');
        $searchConfig = $searchProvider->getConfig($className, $fieldName);
        $searchConfig->set('searchable', $searchable);

        $this->configManager->persist($searchConfig);
    }
}
