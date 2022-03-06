<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\AkeneoBundle\Async\Topics;
use Oro\Bundle\AkeneoBundle\Tools\CacheProviderTrait;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Item\ItemWriterInterface;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\FieldsChanges;
use Oro\Bundle\MessageQueueBundle\Client\BufferedMessageProducer;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

class AsyncWriter implements
    ItemWriterInterface,
    ClosableInterface,
    StepExecutionAwareInterface
{
    use CacheProviderTrait;

    private const VARIANTS_BATCH_SIZE = 25;

    /** @var MessageProducerInterface * */
    private $messageProducer;

    /** @var StepExecution */
    private $stepExecution;

    /** @var int */
    private $size = 0;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function __construct(
        MessageProducerInterface $messageProducer,
        DoctrineHelper $doctrineHelper
    ) {
        $this->messageProducer = $messageProducer;
        $this->doctrineHelper = $doctrineHelper;
    }

    public function initialize()
    {
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

        $jobId = $this->insertJob($jobName);
        $this->createFieldsChanges($jobId, $items, 'items');
        $this->sendMessage($channelId, $jobId, true);
    }

    public function flush()
    {
        $this->size = 0;

        $variants = $this->cacheProvider->fetch('product_variants') ?? [];
        if (!$variants) {
            return;
        }

        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');

        $chunks = array_chunk($variants, self::VARIANTS_BATCH_SIZE, true);

        foreach ($chunks as $key => $chunk) {
            $jobName = sprintf(
                'oro_integration:sync_integration:%s:variants:%s-%s',
                $channelId,
                self::VARIANTS_BATCH_SIZE * $key + 1,
                self::VARIANTS_BATCH_SIZE * $key + count($chunk)
            );

            $jobId = $this->insertJob($jobName);
            $this->createFieldsChanges($jobId, $chunk, 'variants');
            $this->sendMessage($channelId, $jobId);
        }
    }

    private function createFieldsChanges(int $jobId, array &$data, string $key): void
    {
        $em = $this->doctrineHelper->getEntityManager(FieldsChanges::class);
        $fieldsChanges = $em
            ->getRepository(FieldsChanges::class)
            ->findOneBy(['entityId' => $jobId, 'entityClass' => Job::class]);
        if (!$fieldsChanges) {
            $fieldsChanges = new FieldsChanges([]);
            $fieldsChanges->setEntityClass(Job::class);
            $fieldsChanges->setEntityId($jobId);
        }
        $fieldsChanges->setChangedFields([$key => $data]);
        $em->persist($fieldsChanges);
        $em->flush($fieldsChanges);
        $em->clear(FieldsChanges::class);
    }

    private function sendMessage(int $channelId, int $jobId, bool $incrementedRead = false): void
    {
        $this->messageProducer->send(
            Topics::IMPORT_PRODUCTS,
            new Message(
                [
                    'integrationId' => $channelId,
                    'jobId' => $jobId,
                    'connector' => 'product',
                    'connector_parameters' => ['incremented_read' => $incrementedRead],
                ],
                MessagePriority::HIGH
            )
        );

        if ($this->messageProducer instanceof BufferedMessageProducer
            && $this->messageProducer->isBufferingEnabled()) {
            $this->messageProducer->flushBuffer();
        }
    }

    private function getRootJob(): ?int
    {
        $rootJobId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('rootJobId') ?? null;
        if (!$rootJobId) {
            throw new \InvalidArgumentException('Root job id is empty');
        }

        return $rootJobId;
    }

    public function close()
    {
        $this->size = 0;
    }

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    private function insertJob(string $jobName): ?int
    {
        $em = $this->doctrineHelper->getEntityManager(Job::class);
        $tableName = $em->getClassMetadata(Job::class)->getTableName();
        $connection = $em->getConnection();

        $qb = $connection->createQueryBuilder();
        $qb
            ->insert($tableName)
            ->values([
                'name' => ':name',
                'status' => ':status',
                'interrupted' => ':interrupted',
                'created_at' => ':createdAt',
                'root_job_id' => ':rootJob',
            ])
            ->setParameters([
                'name' => $jobName,
                'status' => Job::STATUS_NEW,
                'interrupted' => false,
                'unique' => false,
                'createdAt' => new \DateTime(),
                'rootJob' => $this->getRootJob(),
            ], [
                'name' => Types::STRING,
                'status' => Types::STRING,
                'interrupted' => Types::BOOLEAN,
                'unique' => Types::BOOLEAN,
                'createdAt' => Types::DATETIME_MUTABLE,
                'rootJob' => Types::INTEGER,
            ]);

        if ($connection->getDatabasePlatform() instanceof MySqlPlatform) {
            $qb->setValue('`unique`', ':unique');
        } else {
            $qb->setValue('"unique"', ':unique');
        }

        $qb->execute();

        return $connection->lastInsertId();
    }
}
