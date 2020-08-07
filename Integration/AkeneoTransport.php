<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\Pim\ApiClient\Exception\NotFoundHttpException;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\Intl\Intl;

class AkeneoTransport implements AkeneoTransportInterface
{
    const PAGE_SIZE = 100;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $familyVariants = [];

    /**
     * @var array
     */
    private $families = [];

    /**
     * @var AkeneoClientFactory
     */
    private $clientFactory;

    /**
     * @var AkeneoPimExtendableClientInterface
     */
    private $client;

    /**
     * @var MultiCurrencyConfigProvider
     */
    private $configProvider;

    /**
     * @var AkeneoSettings
     */
    private $transportEntity;

    /**
     * @var AkeneoSearchBuilder
     */
    private $akeneoSearchBuilder;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
        return $this->client->getCategoryApi()->all($pageSize);
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
                []
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
            []
        );
    }

    /**
     * @return \Iterator
     */
    public function getProductModels(int $pageSize)
    {
        $this->initAttributesList();
        $this->initFamilyVariants();

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
            $this->familyVariants
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

    public function downloadAndSaveMediaFile($type, $code)
    {
        $path = $this->getFilePath($type, $code);

        if ($this->filesystem->has($path)) {
            return;
        }

        $oldPath = $this->getOldFilePath($type, $code);
        if ($this->filesystem->has($oldPath)) {
            $this->filesystem->rename($oldPath, $path);

            return;
        }

        try {
            $content = $this->client->getProductMediaFileApi()->download($code)->getContents();
        } catch (NotFoundHttpException $e) {
            $this->logger->critical(
                'Error on downloading media file.',
                ['message' => $e->getMessage(), 'exception' => $e]
            );

            return;
        }

        $this->filesystem->write($path, $content, true);
    }

    protected function getFilePath(string $type, string $code): string
    {
        return sprintf('%s/%s', $type, $code);
    }

    /**
     * @internal BC layer
     */
    protected function getOldFilePath(string $type, string $code): string
    {
        return sprintf('%s/%s', $type, basename($code));
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
}
