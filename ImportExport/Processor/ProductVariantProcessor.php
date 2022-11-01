<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductVariantLink;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        $variantSkus = array_values(array_column($items, 'variant'));

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
            $context->addError(
                $this->translator->trans(
                    'oro.akeneo.error',
                    [
                        '%error%' => $this->translator->trans(
                            'oro.akeneo.validator.product_by_sku.not_found',
                            ['%sku%' => $parentSku],
                            'validators'
                        ),
                        '%item%' => json_encode(
                            $context->getValue('rawItemData'),
                            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                        ),
                    ]
                )
            );

            return null;
        }

        $variantSkusUppercase = array_map(
            function ($variantSku) {
                return mb_strtoupper($variantSku);
            },
            $variantSkus
        );

        $variantSkusUppercase = array_combine($variantSkusUppercase, $items);
        foreach ($parentProduct->getVariantLinks() as $variantLink) {
            $variantProduct = $variantLink->getProduct();
            if (!$variantSkusUppercase) {
                $parentProduct->removeVariantLink($variantLink);
                $variantProduct->setStatus(Product::STATUS_DISABLED);
                $objectManager->remove($variantLink);
                $context->incrementDeleteCount();

                continue;
            }

            if (!array_key_exists($variantProduct->getSkuUppercase(), $variantSkusUppercase)) {
                $parentProduct->removeVariantLink($variantLink);
                $variantProduct->setStatus(Product::STATUS_DISABLED);
                $objectManager->remove($variantLink);
                $context->incrementDeleteCount();

                continue;
            }

            $variantItem = $variantSkusUppercase[$variantProduct->getSkuUppercase()];
            $status = empty($variantItem['enabled']) ? Product::STATUS_DISABLED : Product::STATUS_ENABLED;
            $variantProduct->setStatus($status);

            unset($variantSkusUppercase[$variantProduct->getSkuUppercase()]);
        }

        foreach ($variantSkusUppercase as $variantSku => $variantItem) {
            $variantProduct = $productRepository->findOneBySku($variantSku);
            if (!$variantProduct instanceof Product) {
                $context->incrementErrorEntriesCount();
                $context->addError(
                    $this->translator->trans(
                        'oro.akeneo.error',
                        [
                            '%error%' => $this->translator->trans(
                                'oro.akeneo.validator.product_by_sku.not_found',
                                ['%sku%' => $variantSku],
                                'validators'
                            ),
                            '%item%' => json_encode(
                                $context->getValue('rawItemData'),
                                \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                            ),
                        ]
                    )
                );

                continue;
            }

            $variantLink = new ProductVariantLink();
            $variantLink->setProduct($variantProduct);
            $variantLink->setParentProduct($parentProduct);

            $variantProduct->addParentVariantLink($variantLink);
            $parentProduct->addVariantLink($variantLink);

            $status = empty($variantItem['enabled']) ? Product::STATUS_DISABLED : Product::STATUS_ENABLED;
            $variantProduct->setStatus($status);

            $context->incrementAddCount();

            $objectManager->persist($variantLink);
        }

        $validationErrors = $this->strategyHelper->validateEntity($parentProduct);
        if ($validationErrors) {
            $context->incrementErrorEntriesCount();
            foreach ($validationErrors as $validationError) {
                $context->addError(
                    $this->translator->trans(
                        'oro.akeneo.error',
                        [
                            '%error%' => $validationError,
                            '%item%' => json_encode(
                                $context->getValue('rawItemData'),
                                \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                            ),
                        ]
                    )
                );
            }

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

            $context->incrementErrorEntriesCount();
            $context->addError(
                $this->translator->trans(
                    'oro.akeneo.error',
                    [
                        '%error%' => $this->translator->trans(
                            'oro.akeneo.validator.product_variants.empty',
                            ['%sku%' => $parentSku],
                            'validators'
                        ),
                        '%item%' => json_encode(
                            $context->getValue('rawItemData'),
                            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                        ),
                    ]
                )
            );
        }

        $context->incrementUpdateCount();

        return $parentProduct;
    }
}
