<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class CategoryImportProcessor extends StepExecutionAwareImportProcessor implements MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        $this->memoryCacheProvider->get(
            'category_parent_' . $item['code'],
            function () use ($item) {
                return $item['parent'] ?? null;
            }
        );

        $this->memoryCacheProvider->get(
            'category_' . $item['code'],
            function () {
                return true;
            }
        );

        return parent::process($item);
    }
}
