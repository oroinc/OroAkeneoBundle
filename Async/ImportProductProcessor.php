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

    public function __construct(
        DoctrineHelper $doctrineHelper,
        JobRunner $jobRunner,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        SyncProcessorRegistry $syncProcessorRegistry
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->jobRunner = $jobRunner;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->syncProcessorRegistry = $syncProcessorRegistry;
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
}
