<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\AkeneoBundle\Async\Topics;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\EventListener\AdditionalOptionalListenerManager;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Item\ItemWriterInterface;
use Oro\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\FieldsChanges;
use Oro\Bundle\MessageQueueBundle\Client\BufferedMessageProducer;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Bundle\PlatformBundle\Manager\OptionalListenerManager;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ConfigurableAsyncWriter implements
    ItemWriterInterface,
    StepExecutionAwareInterface,
    MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

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

    private $origins = [];

    private $models = [];

    private $configurable = [];

    /** @var CacheInterface */
    private $cache;

    /** @var MemoryCacheProviderInterface|null */
    private $configurableMemoryCacheProvider;

    public function setConfigurableMemoryCacheProvider(?MemoryCacheProviderInterface $configurableMemoryCacheProvider): void
    {
        $this->configurableMemoryCacheProvider = $configurableMemoryCacheProvider;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

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
        $this->additionalOptionalListenerManager->disableListeners();
        $this->optionalListenerManager->disableListeners($this->optionalListenerManager->getListeners());
    }

    public function write(array $items)
    {
        if (!$this->variants) {
            $this->variants = $this->cache->get('variants', function () {
                return [];
            });
        }
        if (!$this->origins) {
            $this->origins = $this->cache->get('origins', function () {
                return [];
            });
        }
        if (!$this->models) {
            $this->models = $this->cache->get('models', function () {
                return [];
            });
        }

        foreach ($items as $item) {
            $origin = $item['origin'];
            $sku = $item['sku'];

            if (isset($item['family_variant'])) {
                $this->origins[$origin] = $sku;

                if (isset($item['parent'])) {
                    $this->models[$origin] = $item['parent'];
                }

                continue;
            }

            if (!isset($item['parent'])) {
                continue;
            }

            $parent = $item['parent'];
            if (!array_key_exists($parent, $this->origins)) {
                continue;
            }

            $this->variants[$parent][$origin] = [
                'parent' => $this->origins[$parent] ?? $parent,
                'variant' => $sku,
            ];
        }
    }

    public function flush()
    {
        $this->optionalListenerManager->enableListeners($this->optionalListenerManager->getListeners());
        $this->additionalOptionalListenerManager->enableListeners();

        if (!$this->variants) {
            return;
        }

        if ($this->variants) {
            $variants = $this->cache->getItem('variants');
            $variants->set($this->variants);
            $this->cache->save($variants);
        }

        if ($this->origins) {
            $origins = $this->cache->getItem('origins');
            $origins->set($this->origins);
            $this->cache->save($origins);
        }

        if ($this->models) {
            $models = $this->cache->getItem('models');
            $models->set($this->models);
            $this->cache->save($models);
        }

        $updated = $this->cache->getItem('time');
        $updated->set($this->memoryCacheProvider->get('time'));

        $this->cache->save($updated);

        $this->variants = array_intersect_key($this->variants, $this->configurableMemoryCacheProvider->get('akeneo_configurable') ?? []);

        foreach ($this->models as $levelTwo => $levelOne) {
            if (array_key_exists($levelTwo, $this->variants)) {
                foreach ($this->variants[$levelTwo] as $sku => $item) {
                    $item['parent'] = $this->origins[$levelOne] ?? $levelOne;
                    $this->variants[$levelOne][$sku] = $item;

                    $akeneoVariantLevels = $this->memoryCacheProvider->get('akeneo_variant_levels') ?? AkeneoSettings::TWO_LEVEL_FAMILY_VARIANT_BOTH;
                    $this->variants[$levelOne][$sku]['parent_disabled'] = $akeneoVariantLevels === AkeneoSettings::TWO_LEVEL_FAMILY_VARIANT_SECOND_ONLY;
                    $this->variants[$levelTwo][$sku]['parent_disabled'] = $akeneoVariantLevels === AkeneoSettings::TWO_LEVEL_FAMILY_VARIANT_FIRST_ONLY;
                }
            }
        }

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

        $this->variants = [];
        $this->origins = [];
        $this->models = [];
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
            ->executeStatement(
                'SELECT 1 FROM oro_message_queue_job WHERE id = :id LIMIT 1;',
                ['id' => $rootJobId],
                ['id' => Types::INTEGER]
            );

        if (!$hasRootJob) {
            throw new \InvalidArgumentException(sprintf('Root job "%d" missing', $rootJobId));
        }

        $childJob = $connection
            ->executeStatement(
                'SELECT id FROM oro_message_queue_job WHERE root_job_id = :rootJob and name = :name LIMIT 1;',
                ['rootJob' => $rootJobId, 'name' => $jobName],
                ['rootJob' => Types::INTEGER, 'name' => Types::STRING]
            );

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
