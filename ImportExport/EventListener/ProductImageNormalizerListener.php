<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Oro\Bundle\ImportExportBundle\Event\DenormalizeEntityEvent;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

class ProductImageNormalizerListener
{
    public function afterDenormalize(DenormalizeEntityEvent $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof ProductImage) {
            return;
        }

        if (empty($event->getData()['uri'])) {
            return;
        }

        if ($entity->getImage() && !$entity->getImage()->getOriginalFilename()) {
            $entity->getImage()->setOriginalFilename(basename($event->getData()['uri']));
        }
    }
}
