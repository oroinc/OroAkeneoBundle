<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class ProductImportProcessor extends StepExecutionAwareImportProcessor implements ClosableInterface
{
    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->strategy instanceof ClosableInterface) {
            $this->strategy->close();
        }

        if ($this->dataConverter instanceof ClosableInterface) {
            $this->dataConverter->close();
        }
    }
}
