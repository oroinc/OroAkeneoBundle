<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductVariantReader extends IteratorBasedReader
{
    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $variants = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('variants') ?? [];

        $this->stepExecution->setReadCount(count($variants));

        $this->setSourceIterator(new \ArrayIterator($variants));
    }
}
