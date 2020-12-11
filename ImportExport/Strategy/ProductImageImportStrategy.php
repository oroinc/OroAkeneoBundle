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

    protected function validateBeforeProcess($entity)
    {
        $validationErrors = $this->strategyHelper->validateEntity($entity, null, ['import_field_type_akeneo']);
        if ($validationErrors) {
            $this->context->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $this->context);

            return null;
        }

        return $entity;
    }
}
