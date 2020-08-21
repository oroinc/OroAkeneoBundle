<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\AkeneoBundle\ImportExport\AkeneoIntegrationTrait;
use Oro\Bundle\PricingBundle\ImportExport\Strategy\ProductPriceImportStrategy as BaseStrategy;

/**
 * Strategy to import product prices.
 */
class ProductPriceImportStrategy extends BaseStrategy
{
    use ImportStrategyAwareHelperTrait;
    use AkeneoIntegrationTrait;

    /**
     * {@inheritdoc}
     */
    protected function beforeProcessEntity($entity)
    {
        if (
            $entity->getPrice() &&
            !in_array($entity->getPrice()->getCurrency(), $this->getTransport()->getAkeneoActiveCurrencies())
        ) {
            return null;
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function updateContextCounters($entity)
    {
    }
}
