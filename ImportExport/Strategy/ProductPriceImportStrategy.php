<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\PricingBundle\ImportExport\Strategy\ProductPriceImportStrategy as BaseStrategy;

class ProductPriceImportStrategy extends BaseStrategy
{
    /**
     * {@inheritdoc}
     */
    protected function beforeProcessEntity($entity)
    {
        if ($entity->getPrice() &&
            !in_array($entity->getPrice()->getCurrency(), $this->getTransport()->getAkeneoActiveCurrencies())) {
            return null;
        }

        return parent::beforeProcessEntity($entity);
    }

    /**
     * @return null|Transport
     */
    private function getTransport()
    {
        if (!$this->context || false === $this->context->hasOption('channel')) {
            return null;
        }

        $channelId = $this->context->getOption('channel');
        $channel = $this->doctrineHelper->getEntityRepository(Channel::class)->find($channelId);

        if (!$channel) {
            return null;
        }

        return $channel->getTransport();
    }
}
