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

        $itemData = (array)($this->context->getValue('itemData') ?? []);
        /** @var ProductImage $image */
        foreach ($existingProduct->getImages() as $image) {
            if (!$image->getImage()) {
                continue;
            }

            if ($image->getImage()->getOriginalFilename() === $entity->getImage()->getOriginalFilename()) {
                $itemData['image']['uuid'] = $image->getImage()->getUuid();

                $entity = $image;
            }
        }
        $this->context->setValue('itemData', $itemData);

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        $result = parent::afterProcessEntity($entity);
        if (!$result && $entity) {
            $this->processValidationErrors($entity, []);
        }

        return $result;
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
