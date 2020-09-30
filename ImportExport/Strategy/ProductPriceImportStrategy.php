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

        $this->refreshPrice($entity);
        $this->loadProduct($entity);

        return $entity;
    }

    protected function afterProcessEntity($entity)
    {
        $this->refreshPrice($entity);

        // Set version to track prices changed within import
        $version = $this->context->getOption('importVersion');
        if ($version) {
            $entity->setVersion($version);
        }

        return $entity;
    }

    protected function updateContextCounters($entity)
    {
    }
}
