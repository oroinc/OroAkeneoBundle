<?php

namespace Oro\Bundle\AkeneoBundle\Job\Context;

use Akeneo\Bundle\BatchBundle\Entity\JobExecution;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Job\Context\SimpleContextAggregator as BaseAggregator;
use Oro\Bundle\ImportExportBundle\Job\ContextHelper;

class SimpleContextAggregator extends BaseAggregator
{
    /**
     * {@inheritdoc}
     */
    public function getAggregatedContext(JobExecution $jobExecution)
    {
        /** @var ContextInterface $context */
        $context = null;
        $stepExecutions = $jobExecution->getStepExecutions();
        foreach ($stepExecutions as $stepExecution) {
            if (null === $context) {
                $context = $this->contextRegistry->getByStepExecution($stepExecution);
            } else {
                ContextHelper::mergeContextCounters(
                    $context,
                    $this->contextRegistry->getByStepExecution($stepExecution)
                );
                //CUSTOMIZATION START
                $context->addErrors($stepExecution->getErrors());
                //CUSTOMIZATION END
            }
        }

        return $context;
    }
}
