<?php

namespace Oro\Bundle\AkeneoBundle\Async;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Authentication\Token\IntegrationTokenAwareTrait;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Provider\SyncProcessorRegistry;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Job\JobStorage;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ImportProductProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    use IntegrationTokenAwareTrait;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var JobRunner */
    private $jobRunner;

    /** @var LoggerInterface */
    private $logger;

    /** @var SyncProcessorRegistry */
    private $syncProcessorRegistry;

    /** @var JobStorage */
    private $jobStorage;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        JobRunner $jobRunner,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        SyncProcessorRegistry $syncProcessorRegistry,
        JobStorage $jobStorage
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->jobRunner = $jobRunner;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->syncProcessorRegistry = $syncProcessorRegistry;
        $this->jobStorage = $jobStorage;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::IMPORT_PRODUCTS];
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $body = JSON::decode($message->getBody());
        $body = array_replace_recursive(['integrationId' => null, 'jobId' => null], $body);

        if (!$body['integrationId']) {
            $this->logger->critical('The message invalid. It must have integrationId set');

            return self::REJECT;
        }

        /** @var EntityManagerInterface $em */
        $em = $this->doctrineHelper->getEntityManagerForClass(Integration::class);

        /** @var Integration $integration */
        $integration = $em->find(Integration::class, $body['integrationId']);

        if (!$integration) {
            $this->logger->error(
                sprintf('The integration not found: %s', $body['integrationId'])
            );

            return self::REJECT;
        }
        if (!$integration->isEnabled()) {
            $this->logger->error(
                sprintf('The integration is not enabled: %s', $body['integrationId'])
            );

            return self::REJECT;
        }

        //make sure that product variants processing is run after importing all products
        if (!empty($body['connector_parameters']['variants'])) {
            $job  = $this->jobStorage->findJobById($body['jobId']);
            if (!$job) {
                $this->logger->error(
                    sprintf('Could not find job with id: %s', $body['jobId'])
                );

                return self::REJECT;
            }

            $runningChildJobsCount = $this->getRunningChildJobs($body, $job->getRootJob());

            if ($runningChildJobsCount > 0) {
                return self::REQUEUE;
            }
        }

        $this->setTemporaryIntegrationToken($integration);

        $result = $this->jobRunner->runDelayed(
            $body['jobId'],
            function (JobRunner $jobRunner, Job $child) use ($integration, $body) {
                $this->doctrineHelper->refreshIncludingUnitializedRelations($integration);
                $processor = $this->syncProcessorRegistry->getProcessorForIntegration($integration);
                $status = $processor->process(
                    $integration,
                    $body['connector'] ?? null,
                    $body['connector_parameters'] ?? []
                );

                return $status;
            }
        );

        return $result ? self::ACK : self::REJECT;
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getRunningChildJobs(array $body, Job $rootJob): int
    {
        $statuses = [Job::STATUS_NEW, Job::STATUS_RUNNING];
        $variantsJobName = sprintf('oro_integration:sync_integration:%s:variants', $body['integrationId']);
        $queryBuilder = $this->jobStorage->createJobQueryBuilder('j');
        $queryBuilder
            ->select($queryBuilder->expr()->count('j.id'))
            ->andWhere($queryBuilder->expr()->neq('j.name', ':jobName'))
            ->andWhere($queryBuilder->expr()->eq('j.rootJob', ':rootJob'))
            ->andWhere($queryBuilder->expr()->in('j.status', ':statuses'))
            ->setParameters([
                'jobName' => $variantsJobName,
                'statuses' => $statuses,
                'rootJob' => $rootJob
            ]);

        return (int)$queryBuilder->getQuery()->getSingleScalarResult();
    }
}
