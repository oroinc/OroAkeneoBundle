<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Step;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Step\ItemStep as BaseItemStep;

class ItemStep extends BaseItemStep
{
    /**
     * {@inheritdoc}
     */
    public function doExecute(StepExecution $stepExecution)
    {
        $this->initializeStepElements($stepExecution);

        $stepExecutor = new StepExecutor();
        $stepExecutor
            ->setReader($this->reader)
            ->setProcessor($this->processor)
            ->setWriter($this->writer);

        $stepExecutor->execute($this);
        $this->flushStepElements();
        $this->restoreStepElements();
    }
}
