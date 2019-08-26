<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

class CategoryParentProcessor implements ProcessorInterface
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

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param mixed $item
     */
    public function process($item)
    {
        if (!$item instanceof Category) {
            return null;
        }

        $objectManager = $this->registry->getManagerForClass(Category::class);
        $entityState = $objectManager
            ->getUnitOfWork()
            ->getEntityState($item);

        /** @var Channel $channel */
        $channel = $item->getChannel();

        /** @var AkeneoSettings $transport */
        $transport = $channel->getTransport();

        if ($entityState !== UnitOfWork::STATE_MANAGED) {
            $item = $objectManager->merge($item);

            /** @var Channel $channel */
            $channel = $objectManager->getReference(Channel::class, $item->getChannel()->getId());
            $item->setChannel($channel);

            /** @var AkeneoSettings $transport */
            $transport = $channel->getTransport();
        }

        $akeneoCode = $item->getAkeneoCode();
        $parentCode = $this->processedIds[$akeneoCode] ?? null;

        $parent = $item->getParentCategory();
        $rootCategory = $transport->getRootCategory();

        if (!$parentCode && !$parent) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if (!$parentCode && $parent->getId() !== $rootCategory->getId()) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if (!$parentCode && $parent->getId() === $rootCategory->getId()) {
            return $item;
        }

        $parentId = $this->codeIds[$parentCode] ?? null;
        if (!$parentId && !$parent) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if (!$parentId && $parent->getId() !== $rootCategory->getId()) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if (!$parentId && $parent->getId() === $rootCategory->getId()) {
            return $item;
        }

        if ($parent && $parentId !== $parent->getId()) {
            /** @var Category $parent */
            $parent = $objectManager->find(Category::class, $parentId);
            $item->setParentCategory($parent);

            return $item;
        }

        if (!$parent) {
            /** @var Category $parent */
            $parent = $objectManager->find(Category::class, $parentId);
            $item->setParentCategory($parent);

            return $item;
        }

        return $item;
    }

    public function initialize()
    {
        $this->processedIds = $this->cacheProvider->fetch('category') ?? [];
        $this->codeIds = $this->cacheProvider->fetch('category_ids') ?? [];
    }

    public function flush()
    {
        $this->cacheProvider->delete('category');
        $this->cacheProvider->delete('category_ids');
        $this->processedIds = null;
        $this->codeIds = null;
    }
}
