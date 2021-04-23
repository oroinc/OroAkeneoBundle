<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;

class ClosableStrategyEventListener
{
    public function onProcessAfter(StrategyEvent $event)
    {
        if ($event->getStrategy() instanceof ClosableInterface) {
            $event->getStrategy()->close();
        }
    }
}
