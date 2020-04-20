<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

/**
 * @property DoctrineHelper $doctrineHelper
 */
trait AkeneoIntegrationTrait
{
    /** @var AkeneoSettings */
    protected $transport;

    /** @var ContextInterface */
    protected $context;

    public function setImportExportContext(ContextInterface $context)
    {
        $this->context = $context;
    }

    public function getImportExportContext(): ContextInterface
    {
        return $this->context;
    }

    /**
     * @return AkeneoSettings
     */
    private function getTransport()
    {
        if ($this->transport) {
            return $this->transport;
        }

        if (!$this->context || false === $this->context->hasOption('channel')) {
            return null;
        }

        $channel = $this->doctrineHelper->getEntityReference(Channel::class, $this->context->getOption('channel'));

        if (!$channel) {
            return null;
        }

        $this->transport = $channel->getTransport();

        return $this->transport;
    }
}
