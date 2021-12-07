<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\ItemWriterInterface;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\AkeneoBundle\Async\Topics;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\MessageQueueBundle\Client\BufferedMessageProducer;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Bundle\MessageQueueBundle\Job\JobManager;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Job\JobRunner;

class AsyncWriter implements
    ItemWriterInterface,
    ClosableInterface,
    StepExecutionAwareInterface
{
    private const VARIANTS_BATCH_SIZE = 25;

    /** @var JobRunner */
    private $jobRunner;

    /** @var MessageProducerInterface * */
    private $messageProducer;

    /** @var StepExecution */
    private $stepExecution;

    /** @var int */
    private $key = 0;

    /** @var int */
    private $size = 0;

    /** @var CacheProvider */
    private $cacheProvider;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var JobManager */
    private $jobManager;

    public function __construct(
        JobRunner $jobRunner,
        MessageProducerInterface $messageProducer,
        DoctrineHelper $doctrineHelper,
        JobManager $jobManager
    ) {
        $this->jobRunner = $jobRunner;
        $this->messageProducer = $messageProducer;
        $this->doctrineHelper = $doctrineHelper;
        $this->jobManager = $jobManager;
    }

    public function initialize()
    {
        $this->key = 1;
        $this->size = 0;
    }

    public function write(array $items)
    {
        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');

        $newSize = $this->size + count($items);
        $jobName = sprintf(
            'oro_integration:sync_integration:%s:products:%s-%s',
            $channelId,
            $this->size + 1,
            $newSize
        );
        $this->size = $newSize;

        $this->stepExecution->setWriteCount($this->size);

        $jobRunner = $this->jobRunner->getJobRunnerForChildJob($this->getRootJob());

        try {
            /** @var Job $child */
            $child = $jobRunner->createDelayed(
                $jobName,
                function (JobRunner $jobRunner, Job $child): Job {
                    return $child;
                }
            );

            $child->setData(['items' => $items]);
            $this->jobManager->saveJob($child);
            $this->doctrineHelper->getEntityManager($child)->clear();

            $this->messageProducer->send(
                Topics::IMPORT_PRODUCTS,
                new Message(
                    [
                        'integrationId' => $channelId,
                        'jobId' => $child->getId(),
                        'connector' => 'product',
                        'connector_parameters' => ['incremented_read' => true],
                    ],
                    MessagePriority::HIGH
                )
            );

            if ($this->messageProducer instanceof BufferedMessageProducer
                && $this->messageProducer->isBufferingEnabled()) {
                $this->messageProducer->flushBuffer();
            }
        } finally {
            $this->key++;
        }
    }

    public function flush()
    {
        $this->key = 1;
        $this->size = 0;

        $variants = $this->cacheProvider->fetch('product_variants') ?? [];
        if (!$variants) {
            return;
        }

        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');

        $jobRunner = $this->jobRunner->getJobRunnerForChildJob($this->getRootJob());

        try {
            $chunks = array_chunk($variants, self::VARIANTS_BATCH_SIZE, true);

            foreach ($chunks as $key => $chunk) {
                $jobName = sprintf(
                    'oro_integration:sync_integration:%s:variants:%s-%s',
                    $channelId,
                    self::VARIANTS_BATCH_SIZE * $key + 1,
                    self::VARIANTS_BATCH_SIZE * $key + count($chunk)
                );

                /** @var Job $child */
                $child = $jobRunner->createDelayed(
                    $jobName,
                    function (JobRunner $jobRunner, Job $child): Job {
                        return $child;
                    }
                );

                $child->setData(['variants' => $chunk]);
                $this->jobManager->saveJob($child);
                $this->doctrineHelper->getEntityManager($child)->clear();

                $this->messageProducer->send(
                    Topics::IMPORT_PRODUCTS,
                    new Message(
                        [
                            'integrationId' => $channelId,
                            'jobId' => $child->getId(),
                            'connector' => 'product',
                            'connector_parameters' => ['incremented_read' => false],
                        ],
                        MessagePriority::HIGH
                    )
                );

                if ($this->messageProducer instanceof BufferedMessageProducer
                    && $this->messageProducer->isBufferingEnabled()) {
                    $this->messageProducer->flushBuffer();
                }
            }
        } finally {
        }
    }

    private function getRootJob(): Job
    {
        $rootJobId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('rootJobId') ?? null;
        if (!$rootJobId) {
            throw new \InvalidArgumentException('Root job id is empty');
        }

        $rootJob = $this->doctrineHelper->getEntityManager(Job::class)->getReference(Job::class, $rootJobId);
        if (!$rootJob) {
            throw new \InvalidArgumentException('Root job is empty');
        }

        return $rootJob;
    }

    public function close()
    {
        $this->key = 1;
        $this->size = 0;
    }

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    public function setCacheProvider(CacheProvider $cacheProvider): void
    {
        $this->cacheProvider = $cacheProvider;
    }
}
