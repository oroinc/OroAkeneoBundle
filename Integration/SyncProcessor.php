<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Provider\LoggerStrategyAwareInterface;
use Oro\Bundle\IntegrationBundle\Provider\SyncProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobProcessor;
use Psr\Log\NullLogger;

class SyncProcessor implements SyncProcessorInterface, LoggerStrategyAwareInterface
{
    /** @var JobProcessor */
    private $jobProcessor;

    /** @var SyncProcessorInterface */
    private $syncProcessor;

    /** @var NullLogger */
    private $logger;

    public function __construct(JobProcessor $jobProcessor, SyncProcessorInterface $syncProcessor)
    {
        $this->jobProcessor = $jobProcessor;
        $this->syncProcessor = $syncProcessor;
        $this->logger = new NullLogger();
    }

    /** {@inheritdoc} */
    public function process(Integration $integration, $connector, array $connectorParameters = [])
    {
        if ($integration->getType() !== AkeneoChannel::TYPE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Wrong integration type, "%s" expected, "%s" given',
                    AkeneoChannel::TYPE,
                    $integration->getType()
                )
            );
        }

        $jobName = 'oro_integration:sync_integration:' . $integration->getId();

        $existingJob = $this->jobProcessor->findRootJobByJobNameAndStatuses(
            $jobName,
            [Job::STATUS_NEW, Job::STATUS_RUNNING]
        );

        if (!$existingJob) {
            return false;
        }

        $connectorParameters['rootJob'] = $existingJob;

        return $this->syncProcessor->process($integration, $connector, $connectorParameters);
    }

    /** {@inheritdoc} */
    public function getLoggerStrategy()
    {
        if ($this->syncProcessor instanceof LoggerStrategyAwareInterface) {
            return $this->syncProcessor->getLoggerStrategy();
        }

        return $this->logger;
    }
}
