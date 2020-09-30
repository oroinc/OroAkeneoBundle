<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

/**
 * Strategy to import product images.
 */
class ProductImageImportStrategy extends ConfigurableAddOrReplaceStrategy
{
    use ImportStrategyAwareHelperTrait;

    /**
     * @param ProductImage $entity
     *
     * @return object
     */
    protected function beforeProcessEntity($entity)
    {
        if (!$entity->getImage()) {
            return null;
        }

        if (!$entity->getProduct()) {
            return null;
        }

        $existingProduct = $this->findExistingEntity($entity->getProduct());
        if (!$existingProduct) {
            return null;
        }

        return $entity;
    }

    protected function afterProcessEntity($entity)
    {
        return $entity;
    }

    protected function updateContextCounters($entity)
    {
    }
}
