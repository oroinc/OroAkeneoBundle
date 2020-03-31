<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransportInterface;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;

/**
 * @property AkeneoTransportInterface $transport
 */
class AttributeConnector extends AbstractOroAkeneoConnector
{
    const IMPORT_JOB_NAME = 'akeneo_attribute_import';
    const PAGE_SIZE = 25;

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'oro.akeneo.connector.attribute.label';
    }

    /**
     * {@inheritdoc}
     */
    public function getImportEntityFQCN()
    {
        return FieldConfigModel::class;
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
        return 'attribute';
    }

    /**
     * {@inheritdoc}
     */
    protected function getConnectorSource()
    {
        return $this->transport->getAttributes(self::PAGE_SIZE);
    }
}
