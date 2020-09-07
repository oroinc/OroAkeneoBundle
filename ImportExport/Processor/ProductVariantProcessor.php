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
use Symfony\Component\Translation\TranslatorInterface;

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

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(
        ManagerRegistry $registry,
        ImportStrategyHelper $strategyHelper,
        ContextRegistry $contextRegistry,
        TranslatorInterface $translator
    ) {
        $this->registry = $registry;
        $this->strategyHelper = $strategyHelper;
        $this->contextRegistry = $contextRegistry;
        $this->translator = $translator;
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
        $variantSkus = array_values(array_filter(array_column($items, 'variant')));

        $parentSku = reset($parentSkus);

        $context = $this->contextRegistry->getByStepExecution($this->stepExecution);
        $context->setValue('rawItemData', ['configurable' => $parentSku, 'variants' => $variantSkus]);
        $context->setValue('itemData', ['configurable' => $parentSku, 'variants' => $variantSkus]);

        $objectManager = $this->registry->getManagerForClass(Product::class);
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->getRepository(Product::class);

        $parentProduct = $productRepository->findOneBySku($parentSku);
        if (!$parentProduct instanceof Product) {
            $context->incrementErrorEntriesCount();

            $errorMessages = [$this->translator->trans('oro.product.product_by_sku.not_found', [], 'validators')];
            $this->strategyHelper->addValidationErrors($errorMessages, $context);

            return null;
        }

        $variantSkusUppercase = array_map(
            function ($variantSku) {
                return mb_strtoupper($variantSku);
            },
            $variantSkus
        );

        $variantSkusUppercase = array_combine($variantSkusUppercase, $variantSkusUppercase);
        foreach ($parentProduct->getVariantLinks() as $variantLink) {
            $variantProduct = $variantLink->getProduct();
            if (!$variantSkusUppercase) {
                $parentProduct->removeVariantLink($variantLink);
                $variantProduct->setStatus(Product::STATUS_DISABLED);
                $objectManager->remove($variantLink);
                continue;
            }

            if (!array_key_exists($variantProduct->getSkuUppercase(), $variantSkusUppercase)) {
                $parentProduct->removeVariantLink($variantLink);
                $variantProduct->setStatus(Product::STATUS_DISABLED);
                $objectManager->remove($variantLink);
                continue;
            }

            $variantProduct->setStatus(Product::STATUS_ENABLED);

            unset($variantSkusUppercase[$variantProduct->getSkuUppercase()]);
        }

        $variantLinks = [];
        foreach ($variantSkusUppercase as $variantSku) {
            $variantProduct = $productRepository->findOneBySku($variantSku);
            if ($variantProduct instanceof Product) {
                $variantLink = new ProductVariantLink();
                $variantLink->setProduct($variantProduct);
                $variantLink->setParentProduct($parentProduct);

                $variantProduct->addParentVariantLink($variantLink);
                $parentProduct->addVariantLink($variantLink);

                $variantProduct->setStatus(Product::STATUS_ENABLED);

                $variantLinks[$variantProduct->getSku()] = $variantLink;
            }
        }

        $validationErrors = $this->strategyHelper->validateEntity($parentProduct);
        if ($validationErrors) {
            $context->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $context);

            $objectManager->clear();

            $parentProduct = $productRepository->findOneBySku($parentSku);
            if (!$parentProduct instanceof Product) {
                return null;
            }

            $parentProduct->setStatus(Product::STATUS_DISABLED);

            return $parentProduct;
        }

        if ($parentProduct->getVariantLinks()->isEmpty()) {
            $parentProduct->setStatus(Product::STATUS_DISABLED);
        }

        $context->incrementUpdateCount();

        return $parentProduct;
    }
}
