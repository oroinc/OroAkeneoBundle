<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Gaufrette\Filesystem;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Oro\Bundle\AkeneoBundle\Client\AkeneoClientFactory;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Form\Type\AkeneoSettingsType;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeFamilyIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\ProductIterator;
use Oro\Bundle\AkeneoBundle\Settings\DataProvider\SyncProductsDataProvider;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\MultiCurrencyBundle\Config\MultiCurrencyConfigProvider;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Intl\Intl;

class AkeneoTransport implements AkeneoTransportInterface
{
    use LoggerAwareTrait;

    const PAGE_SIZE = 100;

    private $attributes = [];

    private $familyVariants = [];

    private $families = [];

    private $measureFamilies = [];

    private $attributeMapping = [];

    /** @var AkeneoClientFactory */
    private $clientFactory;

    /** @var AkeneoPimExtendableClientInterface */
    private $client;

    /** @var MultiCurrencyConfigProvider */
    private $configProvider;

    /** @var AkeneoSettings */
    private $transportEntity;

    /** @var AkeneoSearchBuilder */
    private $akeneoSearchBuilder;

    /** @var Filesystem */
    private $filesystem;

    public function __construct(
        AkeneoClientFactory $clientFactory,
        MultiCurrencyConfigProvider $configProvider,
        AkeneoSearchBuilder $akeneoSearchBuilder,
        FilesystemMap $filesystemMap,
        LoggerInterface $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->configProvider = $configProvider;
        $this->akeneoSearchBuilder = $akeneoSearchBuilder;
        $this->filesystem = $filesystemMap->get('importexport');
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function init(Transport $transportEntity, $tokensEnabled = true)
    {
        $this->client = $this->clientFactory->getInstance($transportEntity, $tokensEnabled);
        $this->transportEntity = $transportEntity;
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        $currencies = [];

        foreach ($this->client->getCurrencyApi()->all() as $currency) {
            if (false === $currency['enabled']) {
                continue;
            }

            $currencies[] = $currency['code'];
        }

        return $currencies;
    }

    /**
     * @return array
     */
    public function getMergedCurrencies()
    {
        $currencies = [];
        $oroCurrencies = $this->configProvider->getCurrencies();

        foreach ($this->client->getCurrencyApi()->all() as $currency) {
            if (false === $currency['enabled']) {
                continue;
            }
            if (in_array($currency['code'], $oroCurrencies)) {
                $currencies[$currency['code']] = $currency['code'];
            }
        }

        return $currencies;
    }

    public function setConfigProvider(MultiCurrencyConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    /**
     * @return array
     */
    public function getLocales()
    {
        $locales = [];

        foreach ($this->client->getLocaleApi()->all() as $locale) {
            if (false === $locale['enabled']) {
                continue;
            }

            $localeName = Intl::getLocaleBundle()->getLocaleName($locale['code']);
            $locales[$localeName ?: $locale['code']] = $locale['code'];
        }

        return $locales;
    }

    /**
     * @return array
     */
    public function getChannels()
    {
        $channels = [];
        foreach ($this->client->getChannelApi()->all() as $channel) {
            $channels[$channel['code']] = $channel['code'];
        }

        return $channels;
    }

    /**
     * @return \Iterator
     */
    public function getCategories(int $pageSize)
    {
        $categoryTreeChannel = null;
        $akeneoChannel = $this->transportEntity->getAkeneoActiveChannel();

        if (!empty($akeneoChannel)) {
            foreach ($this->client->getChannelApi()->all() as $channel) {
                $categoryTreeChannel = ($channel['code'] == $akeneoChannel && !empty($channel['category_tree'])) ? $channel['category_tree'] : null;

                if (null !== $categoryTreeChannel) {
                    break;
                }
            }
        }

        if (null === $categoryTreeChannel) {
            return $this->client->getCategoryApi()->all($pageSize);
        }

        $parentCategory = [];
        $akeneoTree = new \ArrayIterator([], \ArrayIterator::STD_PROP_LIST);

        foreach ($this->client->getCategoryApi()->all($pageSize) as $category) {
            if ($category['code'] == $categoryTreeChannel || in_array($category['parent'], $parentCategory)) {
                $parentCategory[] = $category['code'];
                $akeneoTree->append($category);
            }
        }
        unset($parentCategory);

        return $akeneoTree;
    }

    /**
     * @return \Iterator
     */
    public function getAttributeFamilies()
    {
        return new AttributeFamilyIterator(
            $this->client->getFamilyApi()->all(self::PAGE_SIZE),
            $this->client,
            $this->logger
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return \Iterator
     */
    public function getProducts(int $pageSize)
    {
        $this->initAttributesList();
        $this->initMeasureFamilies();

        $searchFilters = $this->akeneoSearchBuilder->getFilters($this->transportEntity->getProductFilter());

        if ($this->transportEntity->getSyncProducts() === SyncProductsDataProvider::PUBLISHED) {
            return new ProductIterator(
                $this->client->getPublishedProductApi()->all(
                    $pageSize,
                    ['search' => $searchFilters, 'scope' => $this->transportEntity->getAkeneoActiveChannel()]
                ),
                $this->client,
                $this->logger,
                $this->attributes,
                $this->familyVariants,
                $this->measureFamilies,
                $this->getAttributeMapping()
            );
        }

        return new ProductIterator(
            $this->client->getProductApi()->all(
                $pageSize,
                ['search' => $searchFilters, 'scope' => $this->transportEntity->getAkeneoActiveChannel()]
            ),
            $this->client,
            $this->logger,
            $this->attributes,
            $this->familyVariants,
            $this->measureFamilies,
            $this->getAttributeMapping()
        );
    }

    /**
     * @return \Iterator
     */
    public function getProductModels(int $pageSize)
    {
        $this->initAttributesList();
        $this->initFamilyVariants();
        $this->initMeasureFamilies();

        $searchFilters = $this->akeneoSearchBuilder->getFilters($this->transportEntity->getProductFilter());
        if (isset($searchFilters['completeness'])) {
            unset($searchFilters['completeness']);
        }

        return new ProductIterator(
            $this->client->getProductModelApi()->all(
                $pageSize,
                ['search' => $searchFilters, 'scope' => $this->transportEntity->getAkeneoActiveChannel()]
            ),
            $this->client,
            $this->logger,
            $this->attributes,
            $this->familyVariants,
            $this->measureFamilies,
            $this->getAttributeMapping()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsFormType()
    {
        return AkeneoSettingsType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsEntityFQCN()
    {
        return AkeneoSettings::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'oro.akeneo.integration.settings.label';
    }

    /**
     * @return AttributeIterator
     */
    public function getAttributes(int $pageSize)
    {
        $attributeFilter = $this->getAttributeFilter();

        return new AttributeIterator(
            $this->client->getAttributeApi()->all($pageSize),
            $this->client,
            $this->logger,
            $attributeFilter
        );
    }

    private function getAttributeFilter(): array
    {
        $attrList = $this->transportEntity->getAkeneoAttributesList();
        if (!empty($attrList)) {
            return array_merge(
                explode(';', $attrList) ?? [],
                explode(';', $this->transportEntity->getAkeneoAttributesImageList()) ?? []
            );
        }

        $this->initFamilies();

        $familtyAttributes = [];
        foreach ($this->families as $family) {
            $familtyAttributes = array_unique(array_merge($familtyAttributes, $family['attributes'] ?? []));
        }

        return $familtyAttributes;
    }

    public function downloadAndSaveMediaFile(string $code)
    {
        $path = sprintf('akeneo/%s', $code);
        if ($this->filesystem->has($path)) {
            return;
        }

        try {
            $content = $this->client->getProductMediaFileApi()->download($code)->getContents();
        } catch (\Throwable $e) {
            $this->logger->critical(
                'Error on downloading media file.',
                ['message' => $e->getMessage(), 'exception' => $e]
            );

            return;
        }

        try {
            $this->filesystem->write($path, $content, true);
        } catch (\Throwable $e) {
            $this->logger->critical(
                'Error during saving media file.',
                ['message' => $e->getMessage(), 'exception' => $e]
            );

            return;
        }
    }

    protected function initAttributesList()
    {
        if (empty($this->attributes)) {
            $attributeFilter = $this->getAttributeFilter();
            foreach ($this->client->getAttributeApi()->all(self::PAGE_SIZE) as $attribute) {
                if ($attributeFilter && !in_array($attribute['code'], $attributeFilter)) {
                    continue;
                }

                $this->attributes[$attribute['code']] = $attribute;
            }
        }
    }

    protected function initFamilyVariants()
    {
        if (!empty($this->familyVariants)) {
            return;
        }

        $this->initFamilies();

        foreach ($this->families as $family) {
            foreach ($this->client->getFamilyVariantApi()->all($family['code'], self::PAGE_SIZE) as $variant) {
                $variant['family'] = $family['code'];
                $this->familyVariants[$variant['code']] = $variant;
            }
        }
    }

    protected function initFamilies()
    {
        if (!empty($this->families)) {
            return;
        }

        foreach ($this->client->getFamilyApi()->all(self::PAGE_SIZE) as $family) {
            $this->families[$family['code']] = $family;
        }
    }

    protected function initMeasureFamilies()
    {
        if (!empty($this->measureFamilies)) {
            return;
        }

        foreach ($this->client->getMeasureFamilyApi()->all() as $measurementFamily) {
            foreach (($measurementFamily['units'] ?? []) as $unit) {
                $this->measureFamilies[$unit['code']] = $unit['symbol'];
            }
        }
    }

    protected function getAttributeMapping(): array
    {
        if ($this->attributeMapping) {
            return $this->attributeMapping;
        }

        $attributesMappings = trim(
            $this->transportEntity->getAkeneoAttributesMapping() ?? AkeneoSettings::DEFAULT_ATTRIBUTES_MAPPING,
            ';:'
        );

        if (!empty($attributesMappings)) {
            $attributesMapping = explode(';', $attributesMappings);
            foreach ($attributesMapping as $attributeMapping) {
                list($akeneoAttribute, $systemAttribute) = explode(':', $attributeMapping);
                if (!isset($akeneoAttribute, $systemAttribute)) {
                    continue;
                }

                $this->attributeMapping[$systemAttribute] = $akeneoAttribute;
            }
        }

        return $this->attributeMapping;
    }
}
