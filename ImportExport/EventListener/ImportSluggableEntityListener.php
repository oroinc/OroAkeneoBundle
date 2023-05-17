<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Oro\Bundle\EntityBundle\EntityProperty\UpdatedAtAwareInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;
use Oro\Bundle\RedirectBundle\Entity\SluggableInterface;
use Oro\Bundle\RedirectBundle\EventListener\ImportSluggableEntityListener as BaseListener;

/**
 * Check for entity changes before
 */
class ImportSluggableEntityListener extends BaseListener
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

     public function onProcessAfter(StrategyEvent $event): void
     {
         $entity = $event->getEntity();

         if ($entity instanceof SluggableInterface && $entity instanceof UpdatedAtAwareInterface) {
             $uow = $this->doctrineHelper->getEntityManager($entity)->getUnitOfWork();
             if (!$uow->getEntityChangeSet($entity)) {
                 return;
             }

             $entity->setUpdatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
         }
     }
}
