<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\AkeneoBundle\EventListener\CategoryListener;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Writer\PersistentBatchWriter;

class CategoryParentWriter extends PersistentBatchWriter implements ClosableInterface
{
    /** @var CategoryMaterializedPathModifier */
    protected $modifier;

    /** @var CategoryListener */
    protected $categoryListener;

    public function setCategoryMaterializedPathModifier(CategoryMaterializedPathModifier $modifier)
    {
        $this->modifier = $modifier;
    }

    public function setCategoryListener(CategoryListener $categoryListener): void
    {
        $this->categoryListener = $categoryListener;
    }

    public function close()
    {
        $this->modifier->updateMaterializedPathQuery();
    }

    protected function saveItems(array $items, EntityManager $em)
    {
        try {
            $this->modifier->pause();
            $this->categoryListener->setEnabled(false);

            foreach ($items as $item) {
                $em->persist($item);
            }

            $em->flush();
        } finally {
            $this->modifier->restore();
            $this->categoryListener->setEnabled(true);
        }
    }
}
