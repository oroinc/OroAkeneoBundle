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
    use OwnerTrait;

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

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        $this->setOwner($entity);

        return parent::afterProcessEntity($entity);
    }
}
