<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\ItemWriterInterface;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\AkeneoBundle\Async\Topics;
use Oro\Bundle\AkeneoBundle\EventListener\AdditionalOptionalListenerManager;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\FieldsChanges;
use Oro\Bundle\MessageQueueBundle\Client\BufferedMessageProducer;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Bundle\PlatformBundle\Manager\OptionalListenerManager;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

class ConfigurableAsyncWriter implements
    ItemWriterInterface,
    StepExecutionAwareInterface
{
    private const VARIANTS_BATCH_SIZE = 25;

    /** @var MessageProducerInterface * */
    private $messageProducer;

    /** @var StepExecution */
    private $stepExecution;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var OptionalListenerManager */
    private $optionalListenerManager;

    /** @var AdditionalOptionalListenerManager */
    private $additionalOptionalListenerManager;

    private $variants = [];

    public function __construct(
        MessageProducerInterface $messageProducer,
        DoctrineHelper $doctrineHelper,
        OptionalListenerManager $optionalListenerManager,
        AdditionalOptionalListenerManager $additionalOptionalListenerManager
    ) {
        $this->messageProducer = $messageProducer;
        $this->doctrineHelper = $doctrineHelper;
        $this->optionalListenerManager = $optionalListenerManager;
        $this->additionalOptionalListenerManager = $additionalOptionalListenerManager;
    }

    public function initialize()
    {
        $this->variants = [];

        $this->additionalOptionalListenerManager->disableListeners();
        $this->optionalListenerManager->disableListeners($this->optionalListenerManager->getListeners());
    }

    public function write(array $items)
    {
        foreach ($items as $item) {
            $sku = $item['sku'];

            if (!empty($item['family_variant'])) {
                if (isset($item['parent'], $this->variants[$sku])) {
                    $parent = $item['parent'];
                    foreach (array_keys($this->variants[$sku]) as $sku) {
                        $this->variants[$parent][$sku] = ['parent' => $parent, 'variant' => $sku];
                    }
                }

                return;
            }

            if (empty($item['parent'])) {
                return;
            }

            $parent = $item['parent'];

            $this->variants[$parent][$sku] = ['parent' => $parent, 'variant' => $sku];
        }
    }

    public function close()
    {
        $this->variants = [];

        $this->optionalListenerManager->enableListeners($this->optionalListenerManager->getListeners());
        $this->additionalOptionalListenerManager->enableListeners();
    }

    public function flush()
    {
        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');

        $chunks = array_chunk($this->variants, self::VARIANTS_BATCH_SIZE, true);

        foreach ($chunks as $key => $chunk) {
            $jobName = sprintf(
                'oro_integration:sync_integration:%s:variants:%s-%s',
                $channelId,
                self::VARIANTS_BATCH_SIZE * $key + 1,
                self::VARIANTS_BATCH_SIZE * $key + count($chunk)
            );

            $jobId = $this->insertJob($jobName);
            if ($jobId && $this->createFieldsChanges($jobId, $chunk, 'variants')) {
                $this->sendMessage($channelId, $jobId);
            }
        }
    }

    private function createFieldsChanges(int $jobId, array &$data, string $key): bool
    {
        $em = $this->doctrineHelper->getEntityManager(FieldsChanges::class);
        $fieldsChanges = $em
            ->getRepository(FieldsChanges::class)
            ->findOneBy(['entityId' => $jobId, 'entityClass' => Job::class]);
        if ($fieldsChanges) {
            return false;
        }

        $fieldsChanges = new FieldsChanges([]);
        $fieldsChanges->setEntityClass(Job::class);
        $fieldsChanges->setEntityId($jobId);
        $fieldsChanges->setChangedFields([$key => $data]);
        $em->persist($fieldsChanges);
        $em->flush($fieldsChanges);
        $em->clear(FieldsChanges::class);

        return true;
    }

    private function sendMessage(int $channelId, int $jobId, bool $incrementedRead = false): void
    {
        $this->messageProducer->send(
            Topics::IMPORT_PRODUCTS,
            new Message(
                [
                    'integrationId' => $channelId,
                    'jobId' => $jobId,
                    'connector' => 'configurable_product',
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

        return (int)$rootJobId;
    }

    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    private function insertJob(string $jobName): ?int
    {
        $em = $this->doctrineHelper->getEntityManager(Job::class);
        $connection = $em->getConnection();
        $rootJobId = $this->getRootJob();

        $hasRootJob = $connection
            ->executeQuery(
                'SELECT 1 FROM oro_message_queue_job WHERE id = :id LIMIT 1;',
                ['id' => $rootJobId],
                ['id' => Types::INTEGER]
            )->fetchColumn();

        if (!$hasRootJob) {
            throw new \InvalidArgumentException(sprintf('Root job "%d" missing', $rootJobId));
        }

        $childJob = $connection
            ->executeQuery(
                'SELECT id FROM oro_message_queue_job WHERE root_job_id = :rootJob and name = :name LIMIT 1;',
                ['rootJob' => $rootJobId, 'name' => $jobName],
                ['rootJob' => Types::INTEGER, 'name' => Types::STRING]
            )->fetchColumn();

        if ($childJob) {
            return $childJob;
        }

        $qb = $connection->createQueryBuilder();
        $qb
            ->insert('oro_message_queue_job')
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
                'rootJob' => $rootJobId,
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
