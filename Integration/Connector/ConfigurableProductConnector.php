<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransport;
use Oro\Bundle\AkeneoBundle\Placeholder\SchemaUpdateFilter;
use Oro\Bundle\AkeneoBundle\Tools\CacheProviderTrait;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;
use Oro\Bundle\IntegrationBundle\Provider\AllowedConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Integration configurable product connector.
 */
class ConfigurableProductConnector extends AbstractConnector implements ConnectorInterface, AllowedConnectorInterface
{
    use CacheProviderTrait;

    const PAGE_SIZE = 100;

    /** @var AkeneoTransport */
    protected $transport;

    /** @var SchemaUpdateFilter */
    protected $schemaUpdateFilter;

    /** @var CacheInterface */
    private $cache;

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getLabel(): string
    {
        return 'oro.akeneo.connector.configurable_product.label';
    }

    public function getImportEntityFQCN()
    {
        return Product::class;
    }

    public function getImportJobName()
    {
        return 'akeneo_configurable_product_import';
    }

    public function getType()
    {
        return 'configurable_product';
    }

    public function setSchemaUpdateFilter(SchemaUpdateFilter $schemaUpdateFilter): void
    {
        $this->schemaUpdateFilter = $schemaUpdateFilter;
    }

    public function isAllowed(Channel $integration, array $processedConnectorsStatuses): bool
    {
        return $this->schemaUpdateFilter->isApplicable($integration, Product::class) === false;
    }

    protected function getConnectorSource()
    {
        $variants = $this->cacheProvider->fetch('akeneo')['variants'] ?? [];
        if ($variants) {
            return new \ArrayIterator();
        }

        $this->cacheProvider->save('akeneo_variant_levels', $this->channel->getTransport()->getAkeneoVariantLevels());

        $now = time();
        $this->cacheProvider->save('time', $now);

        $sinceLastNDays = 0;
        $time = $this->cache->getItem('time')->get() ?: null;
        if ($time) {
            $nowDate = new \DateTime();
            $nowDate->setTimestamp($now);
            $lastDate = new \DateTime();
            $lastDate->setTimestamp($time);
            $interval = $lastDate->diff($nowDate);
            $sinceLastNDays = (int)$interval->format('%a') ?: 1;
        }

        $iterator = new \AppendIterator();
        $iterator->append($this->transport->getProductModelsList(self::PAGE_SIZE, $sinceLastNDays));
        $iterator->append($this->transport->getProductsList(self::PAGE_SIZE, $sinceLastNDays));

        return $iterator;
    }
}
