<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Step;

use Oro\Bundle\BatchBundle\Exception\InvalidItemException;
use Oro\Bundle\BatchBundle\Step\StepExecutionWarningHandlerInterface;
use Oro\Bundle\BatchBundle\Step\StepExecutor as BaseStepExecutor;

class StepExecutor extends BaseStepExecutor
{
    public function execute(StepExecutionWarningHandlerInterface $warningHandler = null): void
    {
        try {
            $stopExecution = false;
            while (!$stopExecution) {
                try {
                    $readItem = $this->reader->read();
                    if (null === $readItem) {
                        $stopExecution = true;
                        continue;
                    }
                } catch (InvalidItemException $e) {
                    $this->handleStepExecutionWarning($this->reader, $e, $warningHandler);

                    continue;
                }

                $processedItem = $this->process($readItem, $warningHandler);
                $processedItems = $processedItem ? [$processedItem] : [];
                $this->write($processedItems, $warningHandler);
            }
        } finally {
            $this->ensureResourcesReleased($warningHandler);
        }
    }
}
