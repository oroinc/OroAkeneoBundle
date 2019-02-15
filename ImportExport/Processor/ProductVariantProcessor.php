<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductVariantLink;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;

class ProductVariantProcessor implements ProcessorInterface
{
    /** @var ManagerRegistry */
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function process($items)
    {
        $parentSkus = array_column($items, 'parent');
        $variantSkus = array_column($items, 'variant');

        if (!$parentSkus || !$variantSkus) {
            return null;
        }

        $variantSkusUppercase = array_map(
            function ($variantSku) {
                return strtoupper($variantSku);
            },
            array_column($items, 'variant')
        );

        $parentSku = reset($parentSkus);

        $objectManager = $this->registry->getManagerForClass(Product::class);
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->getRepository(Product::class);

        $parentProduct = $productRepository->findOneBySku($parentSku);
        if (!$parentProduct instanceof Product) {
            return null;
        }

        if (!$parentProduct->getId()) {
            return null;
        }

        $hasChanges = false;
        $variantSkusUppercase = array_combine($variantSkusUppercase, $variantSkusUppercase);
        foreach ($parentProduct->getVariantLinks() as $variantLink) {
            if (!array_key_exists($variantLink->getProduct()->getSkuUppercase(), $variantSkusUppercase)) {
                $parentProduct->removeVariantLink($variantLink);
                $objectManager->remove($variantLink);
                $hasChanges = true;
            }

            unset($variantSkusUppercase[$variantLink->getProduct()->getSkuUppercase()]);
        }

        foreach ($variantSkusUppercase as $variantSku) {
            $variantProduct = $productRepository->findOneBySku($variantSku);
            if ($variantProduct instanceof Product && $variantProduct->getId()) {
                $variantLink = new ProductVariantLink();
                $variantLink->setProduct($variantProduct);
                $variantLink->setParentProduct($parentProduct);

                $variantProduct->addParentVariantLink($variantLink);
                $parentProduct->addVariantLink($variantLink);

                $objectManager->persist($variantLink);
                $hasChanges = true;
            }
        }


        if ($hasChanges) {
            return $parentProduct;
        }

        return null;
    }
}
