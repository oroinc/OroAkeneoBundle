<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Gaufrette\Filesystem;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Oro\Bundle\AkeneoBundle\Client\AkeneoClientFactory;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Form\Type\AkeneoSettingsType;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeFamilyIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\CategoryIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\ProductIterator;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\MultiCurrencyBundle\Config\MultiCurrencyConfigProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Intl\Intl;

class AkeneoTransport implements AkeneoTransportInterface
{
    /**
     * @var AkeneoClientFactory
     */
    private $clientFactory;

    /**
     * @var AkeneoPimEnterpriseClientInterface
     */
    private $client;

    /**
     * @var MultiCurrencyConfigProvider
     */
    private $configProvider;

    /**
     * @var Transport
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

    /**
     * @param AkeneoClientFactory $clientFactory
     * @param MultiCurrencyConfigProvider $configProvider
     * @param AkeneoSearchBuilder $akeneoSearchBuilder
     * @param FilesystemMap $filesystemMap
     * @param LoggerInterface $logger
     */
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

    /**
     * @param MultiCurrencyConfigProvider $configProvider
     */
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
     * @param int $pageSize
     *
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
        return new AttributeFamilyIterator($this->client->getFamilyApi()->all(), $this->client, $this->logger);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $pageSize
     *
     * @return \Iterator
     */
    public function getProducts(int $pageSize)
    {
        $searchFilters = $this->akeneoSearchBuilder->getFilters($this->transportEntity->getProductFilter());

        return new ProductIterator(
            $this->client->getProductApi()->all(
                $pageSize,
                ['search' => $searchFilters, 'scope' => $this->transportEntity->getAkeneoActiveChannel()]
            ),
            $this->client,
            $this->logger,
            $this->filesystem,
            $this->getAttributes($pageSize)
        );
    }

    /**
     * @param int $pageSize
     *
     * @return \Iterator
     */
    public function getProductModels(int $pageSize)
    {
        $searchFilters = $this->akeneoSearchBuilder->getFilters($this->transportEntity->getProductFilter());

        return new ProductIterator(
            $this->client->getProductModelApi()->all(
                $pageSize,
                ['search' => $searchFilters, 'scope' => $this->transportEntity->getAkeneoActiveChannel()]
            ),
            $this->client,
            $this->logger,
            $this->filesystem,
            $this->getAttributes($pageSize)
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
     * @param int $pageSize
     * @param null $filter
     *
     * @return \Iterator
     */
    public function getAttributes(int $pageSize)
    {
        $attributeFilter = [];
        $attrList = $this->transportEntity->getAkeneoAttributesList();
        if (!empty($attrList)) {
            $attributeFilter = explode(';', str_replace(' ', '', $attrList));
        }

        return new AttributeIterator($this->client->getAttributeApi()->all($pageSize), $this->client, $this->logger, $attributeFilter);
    }
}
