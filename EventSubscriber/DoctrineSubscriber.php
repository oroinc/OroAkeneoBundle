<?php

namespace Oro\Bundle\AkeneoBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Oro\Bundle\AkeneoBundle\ImportExport\Processor\ProductImportProcessor;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class DoctrineSubscriber implements EventSubscriber, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** {@inheritdoc} */
    public function getSubscribedEvents()
    {
        return [
            Events::postFlush,
        ];
    }

    public function postFlush()
    {
        /** @var ProductImportProcessor $productImportProcessor */
        $productImportProcessor = $this->container->get('oro_akeneo.importexport.processor.product');
        $productImportProcessor->close();
    }
}
