<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductVariantLink;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;

class ProductVariantProcessor implements ProcessorInterface, StepExecutionAwareInterface
{
    /** @var ManagerRegistry */
    private $registry;

    /** @var ImportStrategyHelper */
    private $strategyHelper;

    /** @var StepExecution */
    private $stepExecution;

    /** @var ContextRegistry */
    private $contextRegistry;

    public function __construct(
        ManagerRegistry $registry,
        ImportStrategyHelper $strategyHelper,
        ContextRegistry $contextRegistry
    ) {
        $this->registry = $registry;
        $this->strategyHelper = $strategyHelper;
        $this->contextRegistry = $contextRegistry;
    }

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param mixed $items
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
                return mb_strtoupper($variantSku);
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
                continue;
            }

            unset($variantSkusUppercase[$variantLink->getProduct()->getSkuUppercase()]);
        }

        $variantLinks = [];
        foreach ($variantSkusUppercase as $variantSku) {
            $variantProduct = $productRepository->findOneBySku($variantSku);
            if ($variantProduct instanceof Product && $variantProduct->getId()) {
                $variantLink = new ProductVariantLink();
                $variantLink->setProduct($variantProduct);
                $variantLink->setParentProduct($parentProduct);

                $variantProduct->addParentVariantLink($variantLink);
                $parentProduct->addVariantLink($variantLink);

                $variantLinks[] = $variantLink;
                $hasChanges = true;
            }
        }

        $validationErrors = $this->strategyHelper->validateEntity($parentProduct);
        if ($validationErrors) {
            $context = $this->contextRegistry->getByStepExecution($this->stepExecution);
            $context->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $context);

            foreach ($parentProduct->getVariantLinks() as $variantLink) {
                $parentProduct->removeVariantLink($variantLink);
                $objectManager->remove($variantLink);
            }

            return $parentProduct;
        }

        if ($hasChanges) {
            foreach ($variantLinks as $item) {
                $objectManager->persist($item);
            }

            return $parentProduct;
        }

        return null;
    }
}
