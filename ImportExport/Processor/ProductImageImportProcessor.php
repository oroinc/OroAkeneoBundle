<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductImageType;

class ProductImageImportProcessor extends StepExecutionAwareImportProcessor implements ClosableInterface
{
    public function close()
    {
        if ($this->strategy instanceof ClosableInterface) {
            $this->strategy->close();
        }

        if ($this->dataConverter instanceof ClosableInterface) {
            $this->dataConverter->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process($items)
    {
        $images = [];
        $product = null;
        foreach ($items as $image) {
            $this->context->setValue('rawItemData', $image);

            if ($this->dataConverter) {
                $image = $this->dataConverter->convertToImportFormat($image, false);
            }

            $this->context->setValue('itemData', $image);

            /** @var ProductImage $object */
            $object = $this->serializer->denormalize(
                $image,
                $this->getEntityName(),
                '',
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

            if (!is_a($image->getImage()->getParentEntityClass(), ProductImage::class, true)) {
                $image->setImage(null);

                $product->removeImage($image);

                continue;
            }

            $filename = $image->getImage()->getOriginalFilename();
            if (!in_array($filename, array_keys($images))) {
                $product->removeImage($image);

                continue;
            }

            if ($hasMain && $this->hasType($image, ProductImageType::TYPE_MAIN)) {
                $image->removeType(ProductImageType::TYPE_MAIN);
            }

            if ($hasListing && $this->hasType($image, ProductImageType::TYPE_LISTING)) {
                $image->removeType(ProductImageType::TYPE_LISTING);
            }

            $hasMain = $hasMain || $this->hasType($image, ProductImageType::TYPE_MAIN);
            $hasListing = $hasListing || $this->hasType($image, ProductImageType::TYPE_LISTING);

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

    private function hasType(ProductImage $image, string $type): bool
    {
        foreach ($image->getTypes() as $imageType) {
            if ($imageType->getType() === $type) {
                return true;
            }
        }

        return false;
    }
}
