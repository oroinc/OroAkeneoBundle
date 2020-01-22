<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductImageType;

/**
 * Strategy to import product images.
 */
class ProductImageImportStrategy extends ConfigurableAddOrReplaceStrategy implements ClosableInterface
{
    use ImportStrategyAwareHelperTrait;

    const DUPLICATED_IMAGES = 'duplicated_images';

    /**
     * @var array
     */
    protected $processedSkus = [];

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->processedSkus = [];
    }

    /**
     * @param ProductImage $entity
     *
     * @return object
     */
    protected function beforeProcessEntity($entity)
    {
        $sku = $entity->getProduct()->getSku();

        if (!$entity->getImage()) {
            return null;
        }

        $processedSkus = $this->processedSkus[$sku] ?? 0;
        $processedSkus++;
        if ($processedSkus > 1) {
            $this->removeImageTypes($entity);
        }
        $this->processedSkus[$sku] = $processedSkus;

        return parent::beforeProcessEntity($entity);
    }

    /**
     * Denormalizer sets wrong keys so ProductImage::removeType doesn't work.
     *
     * @param ProductImage $entity
     */
    private function removeImageTypes(ProductImage $entity)
    {
        foreach ($entity->getTypes() as $key => $type) {
            if (in_array($type->getType(), [ProductImageType::TYPE_MAIN, ProductImageType::TYPE_LISTING])) {
                unset($entity->getTypes()[$key]);
            }
        }
    }
}
