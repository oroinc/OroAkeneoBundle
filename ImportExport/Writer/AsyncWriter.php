<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\AkeneoBundle\Async\Topics;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Item\ItemWriterInterface;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Oro\Bundle\MessageQueueBundle\Client\BufferedMessageProducer;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobProcessor;
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

    /** @var JobProcessor */
    private $jobProcessor;

    public function __construct(
        JobRunner $jobRunner,
        MessageProducerInterface $messageProducer,
        JobProcessor $jobProcessor
    ) {
        $this->jobRunner = $jobRunner;
        $this->messageProducer = $messageProducer;
        $this->jobProcessor = $jobProcessor;
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

        $setRootJob = \Closure::bind(
            function ($property, $value) {
                $this->{$property} = $value;
            },
            $this->jobRunner,
            $this->jobRunner
        );

        try {
            $setRootJob('rootJob', $this->getRootJob());

            $this->jobRunner->createDelayed(
                $jobName,
                function (JobRunner $jobRunner, Job $child) use ($items, $channelId) {
                    $this->messageProducer->send(
                        Topics::IMPORT_PRODUCTS,
                        new Message(
                            [
                                'integrationId' => $channelId,
                                'jobId' => $child->getId(),
                                'connector' => 'product',
                                'connector_parameters' => [
                                    'items' => $items,
                                    'incremented_read' => true,
                                ],
                            ],
                            MessagePriority::HIGH
                        )
                    );

                    if ($this->messageProducer instanceof BufferedMessageProducer && $this->messageProducer->isBufferingEnabled()) {
                        $this->messageProducer->flushBuffer();
                    }

                    return true;
                }
            );
        } finally {
            $setRootJob('rootJob', null);

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

        $setRootJob = \Closure::bind(
            function ($property, $value) {
                $this->{$property} = $value;
            },
            $this->jobRunner,
            $this->jobRunner
        );

        try {
            $setRootJob('rootJob', $this->getRootJob());
            $chunks = array_chunk($variants, self::VARIANTS_BATCH_SIZE, true);

            foreach ($chunks as $key => $chunk) {
                $jobName = sprintf(
                    'oro_integration:sync_integration:%s:variants:%s-%s',
                    $channelId,
                    self::VARIANTS_BATCH_SIZE * $key + 1,
                    self::VARIANTS_BATCH_SIZE * $key + count($chunk)
                );
                $this->jobRunner->createDelayed(
                    $jobName,
                    function (JobRunner $jobRunner, Job $child) use ($channelId, $chunk) {
                        $this->messageProducer->send(
                            Topics::IMPORT_PRODUCTS,
                            new Message(
                                [
                                    'integrationId' => $channelId,
                                    'jobId' => $child->getId(),
                                    'connector' => 'product',
                                    'connector_parameters' => [
                                        'variants' => $chunk,
                                    ],
                                ],
                                MessagePriority::HIGH
                            )
                        );

                        return true;
                    }
                );
            }
        } finally {
            $setRootJob('rootJob', null);
        }
    }

    private function getRootJob(): Job
    {
        $rootJobId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('rootJobId') ?? null;
        if (!$rootJobId) {
            throw new \InvalidArgumentException('Root job id is empty');
        }

        $rootJob = $this->jobProcessor->findJobById($rootJobId);
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
