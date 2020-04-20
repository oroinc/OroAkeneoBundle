<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

/**
 * @property DoctrineHelper $doctrineHelper
 * @property ContextInterface $context
 */
trait OwnerTrait
{
    /**
     * @var DefaultOwnerHelper
     */
    protected $ownerHelper;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @param DefaultOwnerHelper $ownerHelper
     */
    public function setOwnerHelper($ownerHelper)
    {
        $this->ownerHelper = $ownerHelper;
    }

    private function setOwner($entity)
    {
        if (!$entity) {
            return;
        }

        if (!$this->channel) {
            $this->channel = $this->doctrineHelper->getEntityReference(
                Channel::class,
                $this->context->getOption('channel')
            );
        }

        $this->ownerHelper->populateChannelOwner($entity, $this->channel);
    }

    private function clearOwnerCache()
    {
        $this->channel = null;
    }
}
