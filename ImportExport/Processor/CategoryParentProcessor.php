<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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

        /** @var Channel $channel */
        $channel = $item->getChannel();

        /** @var AkeneoSettings $transport */
        $transport = $channel->getTransport();

        $akeneoCode = $item->getAkeneoCode();
        $parentCode = $this->processedIds[$akeneoCode] ?? null;

        $parent = $item->getParentCategory();
        $rootCategory = $transport->getRootCategory();

        if (!$parentCode && !$parent) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if ($rootCategory && !$parentCode && $parent->getId() !== $rootCategory->getId()) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if ($rootCategory && !$parentCode && $parent->getId() === $rootCategory->getId()) {
            return $item;
        }

        $parentId = $this->codeIds[$parentCode] ?? null;
        if (!$parentId && !$parent) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if ($rootCategory && !$parentId && $parent->getId() !== $rootCategory->getId()) {
            $item->setParentCategory($rootCategory);

            return $item;
        }

        if ($rootCategory && !$parentId && $parent->getId() === $rootCategory->getId()) {
            return $item;
        }

        /** @var EntityManagerInterface $objectManager */
        $objectManager = $this->registry->getManagerForClass(Category::class);

        if ($parent && $parentId && $parentId !== $parent->getId()) {
            /** @var Category $parent */
            $parent = $objectManager->getReference(Category::class, $parentId);
            $item->setParentCategory($parent);

            return $item;
        }

        if (!$parent && $parentId) {
            /** @var Category $parent */
            $parent = $objectManager->getReference(Category::class, $parentId);
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
