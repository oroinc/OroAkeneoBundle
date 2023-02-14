<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Oro\Bundle\AkeneoBundle\ImportExport\Strategy\DefaultOwnerHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

class OwnerStrategyEventListener
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var DefaultOwnerHelper */
    private $defaultOwnerHelper;

    /** @var Channel */
    private $channel;

    public function __construct(DoctrineHelper $doctrineHelper, DefaultOwnerHelper $defaultOwnerHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->defaultOwnerHelper = $defaultOwnerHelper;
    }

    public function onProcessBefore(StrategyEvent $event)
    {
        $channel = $this->getChannel($event->getContext());
        if (!$channel) {
            return;
        }

        $this->defaultOwnerHelper->populateChannelOwner($event->getEntity(), $channel);
    }

    public function onProcessAfter(StrategyEvent $event)
    {
        $channel = $this->getChannel($event->getContext());
        if (!$channel) {
            return;
        }

        $this->defaultOwnerHelper->populateChannelOwner($event->getEntity(), $channel);
    }

    protected function getChannel(ContextInterface $context)
    {
        if (!$this->channel && $context->getOption('channel')) {
            $this->channel = $this->doctrineHelper->getEntityReference(
                Channel::class,
                $context->getOption('channel')
            );
        }

        return $this->channel;
    }

    public function onClear()
    {
        $this->channel = null;
    }
}
