<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductVariantReader extends IteratorBasedReader implements MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $variants = $this->memoryCacheProvider->get('akeneo_variants') ?? [];

        $this->stepExecution->setReadCount(count($variants));

        $this->setSourceIterator(new \ArrayIterator($variants));
    }
}
