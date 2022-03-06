<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\AkeneoBundle\Tools\CacheProviderTrait;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductVariantReader extends IteratorBasedReader
{
    use CacheProviderTrait;

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $variants = $this->cacheProvider->fetch('akeneo')['variants'] ?? [];

        $this->stepExecution->setReadCount(count($variants));

        $this->setSourceIterator(new \ArrayIterator($variants));
    }
}
