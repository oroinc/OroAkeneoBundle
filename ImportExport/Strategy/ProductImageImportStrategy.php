<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

/**
 * Strategy to import product images.
 */
class ProductImageImportStrategy extends ConfigurableAddOrReplaceStrategy implements ClosableInterface
{
    use StrategyValidationTrait;

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

    protected function updateContextCounters($entity)
    {
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
        if ($entity instanceof Product) {
            if (array_key_exists($entity->getSku(), $this->existingProducts)) {
                return $this->existingProducts[$entity->getSku()];
            }

            $entity = $this->doctrineHelper->getEntityRepository($entity)->findByCaseInsensitive(
                [
                    'sku' => $entity->getSku(),
                    'organization' => $entity->getOrganization() ?: $this->getChannel()->getOrganization(),
                ]
            );
            if (is_array($entity)) {
                $entity = array_shift($entity);
                if ($entity instanceof Product) {
                    $this->existingProducts[$entity->getSku()] = $entity;

                    return $entity;
                }

                return null;
            }

            return $entity;
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Product) {
            if (array_key_exists($entity->getSku(), $this->existingProducts)) {
                return $this->existingProducts[$entity->getSku()];
            }

            $entity = $this->doctrineHelper->getEntityRepository($entity)->findByCaseInsensitive(
                [
                    'sku' => $entity->getSku(),
                    'organization' => $entity->getOrganization() ?: $this->getChannel()->getOrganization(),
                ]
            );
            if (is_array($entity)) {
                $entity = array_shift($entity);
                if ($entity instanceof Product) {
                    $this->existingProducts[$entity->getSku()] = $entity;

                    return $entity;
                }

                return null;
            }

            return $entity;
        }

        return parent::findExistingEntityByIdentityFields($entity, $searchContext);
    }

    private function getChannel()
    {
        return $this->doctrineHelper->getEntityReference(Channel::class, $this->context->getOption('channel'));
    }
}
