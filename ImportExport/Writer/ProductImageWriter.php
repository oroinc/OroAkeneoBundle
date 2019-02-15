<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\IntegrationBundle\ImportExport\Writer\PersistentBatchWriter;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

class ProductImageWriter extends PersistentBatchWriter
{
    /** @var array */
    private $products;

    /** @var array */
    private $images;

    public function initialize()
    {
        $this->products = [];
        $this->images = [];
    }

    public function flush()
    {
        if (!$this->products && !$this->images) {
            return;
        }

        $this->registry
            ->getRepository(ProductImage::class)
            ->createQueryBuilder('pi')
            ->delete(ProductImage::class, 'pi')
            ->andWhere('IDENTITY(pi.product) in (:ids)')
            ->andWhere('IDENTITY(pi.image) not in (:images)')
            ->setParameter('ids', $this->products)
            ->setParameter('images', $this->images)
            ->getQuery()
            ->execute();

        unset($this->products);
        unset($this->images);
    }

    protected function saveItems(array $items, EntityManager $em)
    {
        parent::saveItems($items, $em);

        /** @var ProductImage $item */
        foreach ($items as &$item) {
            $this->products[$item->getProduct()->getId()] = $item->getProduct()->getId();
            $this->images[] = $item->getImage()->getId();
        }
    }
}
