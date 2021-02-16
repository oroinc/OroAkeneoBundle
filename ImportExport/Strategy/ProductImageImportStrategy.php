<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

/**
 * Strategy to import product images.
 */
class ProductImageImportStrategy extends ConfigurableAddOrReplaceStrategy implements ClosableInterface
{
    use ImportStrategyAwareHelperTrait;

    /**
     * @var Product[]
     */
    private $existingProducts = [];

    public function close()
    {
        $this->existingProducts = [];
    }

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

            if (!is_a($image->getImage()->getParentEntityClass(), ProductImage::class, true)) {
                continue;
            }

            if ($image->getImage()->getOriginalFilename() === $entity->getImage()->getOriginalFilename()) {
                $itemData['image']['uuid'] = $image->getImage()->getUuid();

                $this->fieldHelper->setObjectValue($entity, 'id', $image->getId());
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

    protected function isFieldExcluded($entityName, $fieldName, $itemData = null)
    {
        $excludeImageFields = ['updatedAt', 'types'];

        if (is_a($entityName, ProductImage::class, true) && in_array($fieldName, $excludeImageFields)) {
            return true;
        }

        return parent::isFieldExcluded($entityName, $fieldName, $itemData);
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Product && array_key_exists($entity->getSku(), $this->existingProducts)) {
            return $this->existingProducts[$entity->getSku()];
        }

        $entity = parent::findExistingEntity($entity, $searchContext);

        if ($entity instanceof Product) {
            $this->existingProducts[$entity->getSku()] = $entity;
        }

        return $entity;
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Product && array_key_exists($entity->getSku(), $this->existingProducts)) {
            return $this->existingProducts[$entity->getSku()];
        }

        $entity = parent::findExistingEntityByIdentityFields($entity, $searchContext);

        if ($entity instanceof Product) {
            $this->existingProducts[$entity->getSku()] = $entity;
        }

        return $entity;
    }
}
