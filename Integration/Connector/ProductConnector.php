<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransport;
use Oro\Bundle\AkeneoBundle\Placeholder\SchemaUpdateFilter;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;
use Oro\Bundle\IntegrationBundle\Provider\AllowedConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * @property AkeneoTransport $transport
 */
class ProductConnector extends AbstractConnector implements ConnectorInterface, AllowedConnectorInterface
{
    const IMPORT_JOB_NAME = 'akeneo_product_import';
    const PAGE_SIZE = 100;
    const TYPE = 'product';

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var FieldHelper
     */
    protected $fieldHelper;

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
    public function isAllowed(Channel $integration, array $processedConnectorsStatuses)
    {
        $fields = $this->fieldHelper->getFields(Product::class, true);
        $importExportProvider = $this->configManager->getProvider('importexport');
        $extendProvider = $this->configManager->getProvider('extend');
        $hasAkeneoFields = false;

        foreach ($fields as $field) {
            if (false === $this->configManager->hasConfig(Product::class, $field['name'])) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);

            if ('akeneo' !== $importExportConfig->get('source')) {
                continue;
            }

            $hasAkeneoFields = true;
            $extendConfig = $extendProvider->getConfig(Product::class, $field['name']);

            if (!in_array($extendConfig->get('state'), [ExtendScope::STATE_ACTIVE, ExtendScope::STATE_DELETE])) {
                return false;
            }
        }

        return $hasAkeneoFields && !$this->needToUpdateSchema($integration);
    }

    /**
     * @param ConfigManager $configManager
     */
    public function setConfigManager(ConfigManager $configManager): void
    {
        $this->configManager = $configManager;
    }

    /**
     * @param FieldHelper $fieldHelper
     */
    public function setFieldHelper(FieldHelper $fieldHelper): void
    {
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * @param SchemaUpdateFilter $schemaUpdateFilter
     */
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
     *
     * @param Channel $integration
     * @return bool
     */
    private function needToUpdateSchema(Channel $integration): bool
    {
        return $this->schemaUpdateFilter->isApplicable($integration, Product::class);
    }
}
