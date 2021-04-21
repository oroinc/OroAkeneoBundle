<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\ImportExportBundle\Event\DenormalizeEntityEvent;

class FileNormalizerListener
{
    public function afterDenormalize(DenormalizeEntityEvent $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof File) {
            return;
        }

        if (empty($event->getData()['uri'])) {
            return;
        }

        if (!$entity->getOriginalFilename()) {
            $entity->setOriginalFilename(basename($event->getData()['uri']));
        }
    }
}
