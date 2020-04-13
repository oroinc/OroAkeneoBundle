<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Placeholder\SchemaUpdateFilter;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Provider\AllowedConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\ProductBundle\Entity\Product;
use Psr\Log\LoggerAwareInterface;

/**
 * Integration product connector.
 */
class ProductConnector extends AbstractOroAkeneoConnector implements ConnectorInterface, AllowedConnectorInterface
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
        $iterator->append($this->transport->getProducts(self::PAGE_SIZE, $this->getLastSyncDate()));
        $iterator->append($this->transport->getProductModels(self::PAGE_SIZE, $this->getLastSyncDate()));

        return $iterator;
    }

    /**
     * Checks if schema is changed and need to update it.
     */
    private function needToUpdateSchema(Channel $integration): bool
    {
        return $this->schemaUpdateFilter->isApplicable($integration, Product::class);
    }

    protected function initializeFromContext(ContextInterface $context)
    {
        $this->transport = $this->contextMediator->getTransport($context, true);
        $this->channel = $this->contextMediator->getChannel($context);

        $status = $this->getLastCompletedIntegrationStatus($this->channel, $this->getType());
        $this->addStatusData(self::LAST_SYNC_KEY, $status->getData()[self::LAST_SYNC_KEY] ?? null);

        $this->validateConfiguration();
        $this->transport->init($this->channel->getTransport());
        $this->setSourceIterator($this->getConnectorSource());

        if ($this->getSourceIterator() instanceof LoggerAwareInterface) {
            $this->getSourceIterator()->setLogger($this->logger);
        }
    }

    public function supportsForceSync()
    {
        return true;
    }
}
