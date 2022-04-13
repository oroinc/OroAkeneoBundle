<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class AsyncProcessor implements ProcessorInterface
{
    public function process($item)
    {
        return $item;
    }
}
