<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\AkeneoBundle\ImportExport\Strategy\ProductImageImportStrategy;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
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

        $this->products = null;
        $this->images = null;

        $this
            ->getImportContext()
            ->setValue(ProductImageImportStrategy::DUPLICATED_IMAGES, null);
    }

    /**
     * @return ContextInterface
     */
    private function getImportContext()
    {
        return $this->contextRegistry->getByStepExecution($this->stepExecution);
    }

    protected function saveItems(array $items, EntityManager $em)
    {
        parent::saveItems($items, $em);

        /** @var ProductImage $item */
        foreach ($items as &$item) {
            $this->products[$item->getProduct()->getId()] = $item->getProduct()->getId();
            $this->images[] = $item->getImage()->getId();

            $this->addDuplicates($item);
        }
    }

    /**
     * @param ProductImage $item
     *
     * @return void
     */
    private function addDuplicates(ProductImage $item): void
    {
        $duplicateImages = (array)$this
            ->getImportContext()
            ->getValue(ProductImageImportStrategy::DUPLICATED_IMAGES);

        if (isset($duplicateImages[$item->getProduct()->getId()])) {
            /** @var \Oro\Bundle\AttachmentBundle\Entity\File $image */
            foreach ($duplicateImages[$item->getProduct()->getId()] as $image) {
                $this->images[] = $image;
            }
        }

        $this->images = array_unique($this->images);
    }
}
