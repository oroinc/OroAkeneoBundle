<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\IntegrationBundle\ImportExport\Writer\PersistentBatchWriter;

class CategoryWriter extends PersistentBatchWriter
{
    /** @var CategoryMaterializedPathModifier */
    protected $modifier;

    public function setCategoryMaterializedPathModifier(CategoryMaterializedPathModifier $modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @param array $items
     * @param EntityManager $em
     */
    protected function saveItems(array $items, EntityManager $em)
    {
        try {
            $this->modifier->pause();

            foreach ($items as $item) {
                $em->persist($item);
            }

            $em->flush();
        } finally {
            $this->modifier->restore();
        }
    }
}
