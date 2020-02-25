<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\ImportExport\Writer\PersistentBatchWriter;

class FamilyWriter extends PersistentBatchWriter
{
    /** @var CacheProvider */
    private $cacheProvider;

    public function setCacheProvider(CacheProvider $cacheProvider): void
    {
        $this->cacheProvider = $cacheProvider;
    }

    public function flush()
    {
        $fieldsMapping = $this->cacheProvider->fetch('attribute_familyVariants') ?? [];
        $this->cacheProvider->delete('attribute_familyVariants');
        $entityManager = $this->registry->getManager();
        //clear for reseting isReadOnly flag
        $entityManager->clear(Channel::class);
        $channelId = $this->stepExecution->getJobExecution()->getExecutionContext()->get('channel');
        $channel = $this->registry
            ->getRepository(Channel::class)
            ->find($channelId);
        if ($channel && $channel->getTransport() instanceof AkeneoSettings) {
            $channel->getTransport()->setAkeneoFamilyVariantMapping($fieldsMapping);
            $entityManager->persist($channel);
            $entityManager->flush();
            $unitOfWork = $this->registry->getEntityManager()->getUnitOfWork();
            $unitOfWork->markReadOnly($channel);
        }
    }
}
