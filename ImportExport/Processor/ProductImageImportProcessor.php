<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductImageType;

class ProductImageImportProcessor extends StepExecutionAwareImportProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process($items)
    {
        $images = [];
        $product = null;
        foreach ($items as $image) {
            if ($this->dataConverter) {
                $image = $this->dataConverter->convertToImportFormat($image, false);
            }

            /** @var ProductImage $object */
            $object = $this->serializer->deserialize(
                $image,
                $this->getEntityName(),
                null,
                array_merge(
                    $this->context->getConfiguration(),
                    [
                        'entityName' => $this->getEntityName(),
                    ]
                )
            );

            if ($this->strategy) {
                $object = $this->strategy->process($object);
                if ($object) {
                    $product = $object->getProduct();
                    $images[$object->getImage()->getOriginalFilename()] = $object;
                }
            }
        }

        if (!$product) {
            return null;
        }

        return $this->mergeImages($product, $images);
    }

    /**
     * @param ProductImage[] $images
     */
    private function mergeImages(Product $product, array $images): Product
    {
        $hasMain = false;
        $hasListing = false;
        $image = null;

        foreach ($product->getImages() as $image) {
            if (!$image->getImage()) {
                $product->removeImage($image);

                continue;
            }

            $filename = $image->getImage()->getOriginalFilename();
            if (!in_array($filename, array_keys($images))) {
                $product->removeImage($image);

                continue;
            }

            $hasMain = $hasMain || $image->hasType(ProductImageType::TYPE_MAIN);
            $hasListing = $hasListing || $image->hasType(ProductImageType::TYPE_LISTING);

            unset($images[$filename]);
        }

        foreach ($images as $image) {
            if (!$image->getImage()) {
                continue;
            }

            $product->addImage($image);
        }

        if (!$hasMain) {
            if ($product->getImages()->first()) {
                $product->getImages()->first()->addType(ProductImageType::TYPE_MAIN);
            }
        }

        if (!$hasListing) {
            if ($product->getImages()->first()) {
                $product->getImages()->first()->addType(ProductImageType::TYPE_LISTING);
            }
        }

        return $product;
    }
}
