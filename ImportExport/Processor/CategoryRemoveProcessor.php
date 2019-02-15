<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class CategoryRemoveProcessor implements ProcessorInterface
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $processedIds;

    /** @var array */
    private $codeIds;

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

        if (array_key_exists($item->getAkeneoCode(), $this->processedIds)) {
            $this->codeIds[$item->getAkeneoCode()] = $item->getId();

            return null;
        }

        $objectManager = $this->registry->getManagerForClass(Category::class);
        $entityState = $objectManager
            ->getUnitOfWork()
            ->getEntityState($item);

        if ($entityState !== UnitOfWork::STATE_MANAGED) {
            $item = $objectManager->merge($item);
        }

        return $item;
    }

    public function initialize()
    {
        $this->processedIds = $this->cacheProvider->fetch('category') ?? [];
        $this->codeIds = [];
    }

    public function flush()
    {
        $this->cacheProvider->save('category_ids', $this->codeIds);
        unset($this->processedIds);
        unset($this->codeIds);
    }
}
