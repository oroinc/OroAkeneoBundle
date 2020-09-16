<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransportInterface;
use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;
use Oro\Bundle\ProductBundle\Entity\Brand;

/**
 * @property AkeneoTransportInterface $transport
 */
class BrandConnector extends AbstractConnector
{
    public function getLabel()
    {
        return 'oro.akeneo.connector.brand.label';
    }

    public function getImportEntityFQCN()
    {
        return Brand::class;
    }

    public function getImportJobName()
    {
        return 'akeneo_brand_import';
    }

    public function getType()
    {
        return 'brand';
    }

    protected function getConnectorSource()
    {
        return $this->transport->getBrands();
    }
}
