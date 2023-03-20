<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class CategoryRemoveProcessor implements ProcessorInterface, MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

    /** @var ManagerRegistry */
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function process($item)
    {
        if (!$item instanceof Category) {
            return null;
        }

        $id = $item->getId();
        $this->memoryCacheProvider->get(
            'category_id_' . $item->getAkeneoCode(),
            function () use ($id) {
                return $id;
            }
        );

        if ($this->memoryCacheProvider->get('category_' . $item->getAkeneoCode())) {
            return null;
        }

        return $item;
    }
}
