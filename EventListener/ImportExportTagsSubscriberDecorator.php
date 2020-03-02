<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Oro\Bundle\ImportExportBundle\Event\AfterEntityPageLoadedEvent;
use Oro\Bundle\ImportExportBundle\Event\DenormalizeEntityEvent;
use Oro\Bundle\ImportExportBundle\Event\LoadEntityRulesAndBackendHeadersEvent;
use Oro\Bundle\ImportExportBundle\Event\LoadTemplateFixturesEvent;
use Oro\Bundle\ImportExportBundle\Event\NormalizeEntityEvent;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;
use Oro\Bundle\TagBundle\EventListener\ImportExportTagsSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tags lazy processing
 */
class ImportExportTagsSubscriberDecorator implements AdditionalOptionalListenerInterface, EventSubscriberInterface
{
    use AdditionalOptionalListenerTrait;

    /** @var ImportExportTagsSubscriber */
    protected $innerSubscriber;

    public function __construct(ImportExportTagsSubscriber $innerSubscriber)
    {
        $this->innerSubscriber = $innerSubscriber;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return ImportExportTagsSubscriber::getSubscribedEvents();
    }

    public function beforeImport(StrategyEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->beforeImport($event);
    }

    public function afterImport(StrategyEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->afterImport($event);
    }

    public function updateEntityResults(AfterEntityPageLoadedEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->updateEntityResults($event);
    }

    public function denormalizeEntity(DenormalizeEntityEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->denormalizeEntity($event);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->onFlush($args);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->postFlush($args);
    }

    public function normalizeEntity(NormalizeEntityEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->normalizeEntity($event);
    }

    public function loadEntityRulesAndBackendHeaders(LoadEntityRulesAndBackendHeadersEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->loadEntityRulesAndBackendHeaders($event);
    }

    public function addTagsIntoFixtures(LoadTemplateFixturesEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerSubscriber->addTagsIntoFixtures($event);
    }
}
