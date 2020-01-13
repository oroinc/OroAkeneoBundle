<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Component\MessageQueue\Exception\JobRedeliveryException;
use Oro\Component\MessageQueue\Job\Job;

class ProductVariantReader extends IteratorBasedReader
{
    private const WAIT_SECONDS_FOR_OTHER_JOBS = 3;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $variants = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('variants') ?? [];

        $count = 0;
        //wait until all other child jobs will be finished, so we will have actual products data
        while (!empty($variants) && $this->isAnyProductsImportJobRunning() && $count < 1200) {
            $count++;
            sleep(self::WAIT_SECONDS_FOR_OTHER_JOBS);
        }

        $this->stepExecution->setReadCount(count($variants));

        $this->setSourceIterator(new \ArrayIterator($variants));
    }

    /**
     * @return bool
     */
    protected function isAnyProductsImportJobRunning()
    {
        $childJobs = $this->stepExecution
            ->getJobExecution()
            ->getExecutionContext()
            ->get('rootJob')
            ->getChildJobs();

        if (empty($childJobs)) {
            return false;
        }

        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');
        $variantsJobName = sprintf('oro_integration:sync_integration:%s:variants', $channelId);

        $entityManager = $this->doctrineHelper->getEntityManager($childJobs[0]);
        foreach ($childJobs as $childJob) {
            //to be sure that we have actual data of child job loaded from DB
            $entityManager->refresh($childJob);
            $jobName = $childJob->getName();
            $jobStatus = $childJob->getStatus();
            if ($jobName !== $variantsJobName
                && ($jobStatus === Job::STATUS_NEW || $jobStatus == Job::STATUS_RUNNING)
            ) {
                return true;
            }
        }

        return false;
    }
}
