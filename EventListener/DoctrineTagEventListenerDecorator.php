<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Oro\Bundle\SyncBundle\EventListener\DoctrineTagEventListener;

class DoctrineTagEventListenerDecorator implements AdditionalOptionalListenerInterface
{
    use AdditionalOptionalListenerTrait;

    /** @var DoctrineTagEventListener */
    protected $innerListener;

    public function __construct(DoctrineTagEventListener $innerListener)
    {
        $this->innerListener = $innerListener;
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->onFlush($event);
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->postFlush($event);
    }

    public function markSkipped(string $className): void
    {
        $this->innerListener->markSkipped($className);
    }
}
