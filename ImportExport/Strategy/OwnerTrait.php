<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\ImportExport\Helper\DefaultOwnerHelper;

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

        $channelId = $this->context->getOption('channel');
        $channel = $this->doctrineHelper->getEntityRepository(Channel::class)->find($channelId);
        $this->ownerHelper->populateChannelOwner($entity, $channel);
    }
}
