<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
     * @param ProductImage $entity
     *
     * @return object|null
     */
    protected function afterProcessEntity($entity)
    {
        if ($this->isImageDuplicate($entity)) {
            return null;
        }

        return parent::afterProcessEntity($entity);
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

    /**
     * @param ProductImage $entity
     *
     * @return bool
     */
    private function isImageDuplicate(ProductImage $entity): bool
    {
        try {
            $image = $this->doctrineHelper->getEntityRepository($entity)->createQueryBuilder('pi')
                ->select('pi')
                ->leftJoin('pi.image', 'i')
                ->where('pi.product = :product')
                ->andWhere('i.originalFilename = :name')
                ->setParameter('product', $entity->getProduct())
                ->setParameter('name', $entity->getImage()->getOriginalFilename())
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return false;
        } catch (NonUniqueResultException $e) {
            return true;
        }

        $this->addDuplicatedImages($image);
        return true;
    }

    /**
     * @param \Oro\Bundle\ProductBundle\Entity\ProductImage $image
     *
     * @return void
     */
    private function addDuplicatedImages(ProductImage $image): void
    {
        $duplicatedImages = $this->context->getValue(self::DUPLICATED_IMAGES);
        $duplicatedImages = $duplicatedImages ?? [];
        $duplicatedImages[$image->getProduct()->getId()][] = $image->getImage()->getId();

        $this->context->setValue(self::DUPLICATED_IMAGES, $duplicatedImages);
    }
}