<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\Pim\ApiClient\Exception\NotFoundHttpException;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Gaufrette\Filesystem;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Oro\Bundle\AkeneoBundle\Client\AkeneoClientFactory;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Form\Type\AkeneoSettingsType;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeFamilyIterator;
use Oro\Bundle\AkeneoBundle\Integration\Iterator\AttributeIterator;
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
        return new AttributeFamilyIterator($this->client->getFamilyApi()->all(), $this->client, $this->logger);
    }

    /**
     * {@inheritdoc}
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
            $this->transportEntity->getAkeneoAttributesTypeMapping(),
            $this->transportEntity->getAkeneoFamilyVariantMapping()
        );
    }

    /**
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
            $this->transportEntity->getAkeneoAttributesTypeMapping(),
            $this->transportEntity->getAkeneoFamilyVariantMapping()
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
        $attributeFilter = [];
        $attrList = $this->transportEntity->getAkeneoAttributesList();
        if (!empty($attrList)) {
            $attributeFilter = array_merge(
                explode(';', $attrList) ?? [],
                explode(';', $this->transportEntity->getAkeneoAttributesImageList()) ?? []
            );
        }

        return new AttributeIterator($this->client->getAttributeApi()->all($pageSize), $this->client, $this->logger, $attributeFilter);
    }

    public function downloadAndSaveMediaFile($type, $code)
    {
        $path = $this->getFilePath($type, $code);

        if ($this->filesystem->has($path)) {
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
        return sprintf('%s/%s', $type, basename($code));
    }
}
