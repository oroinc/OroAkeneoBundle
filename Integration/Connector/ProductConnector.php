<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Placeholder\SchemaUpdateFilter;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;
use Oro\Bundle\IntegrationBundle\Provider\AllowedConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * Integration product connector.
 */
class ProductConnector extends AbstractConnector implements ConnectorInterface, AllowedConnectorInterface
{
    const IMPORT_JOB_NAME = 'akeneo_product_import';
    const PAGE_SIZE = 100;
    const TYPE = 'product';

    /**
     * @var SchemaUpdateFilter
     */
    protected $schemaUpdateFilter;

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'oro.akeneo.connector.product.label';
    }

    /**
     * {@inheritdoc}
     */
    public function getImportEntityFQCN()
    {
        return Product::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getImportJobName()
    {
        return self::IMPORT_JOB_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return self::TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowed(Channel $integration, array $processedConnectorsStatuses): bool
    {
        return !$this->needToUpdateSchema($integration);
    }

    public function setSchemaUpdateFilter(SchemaUpdateFilter $schemaUpdateFilter): void
    {
        $this->schemaUpdateFilter = $schemaUpdateFilter;
    }

    /**
     * {@inheritdoc}
     */
    protected function getConnectorSource()
    {
        $items = $this->stepExecution
            ->getJobExecution()
            ->getExecutionContext()
            ->get('items');

        if ($items) {
            return new \ArrayIterator();
        }

        $variants = $this->stepExecution
            ->getJobExecution()
            ->getExecutionContext()
            ->get('variants');

        if ($variants) {
            return new \ArrayIterator();
        }

        $iterator = new \AppendIterator();
        $iterator->append($this->transport->getProducts(self::PAGE_SIZE));
        $iterator->append($this->transport->getProductModels(self::PAGE_SIZE));

        return $iterator;
    }

    /**
     * Checks if schema is changed and need to update it.
     */
    private function needToUpdateSchema(Channel $integration): bool
    {
        return $this->schemaUpdateFilter->isApplicable($integration, Product::class);
    }
}
