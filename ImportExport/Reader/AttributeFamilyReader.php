<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedIdentityQueryResultIterator;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class AttributeFamilyReader extends IteratorBasedReader
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $channelId = $this->getStepExecution()->getJobExecution()->getExecutionContext()->get('channel');

        $repo = $this->doctrineHelper->getEntityRepository(AttributeFamily::class);

        $qb = $repo
            ->createQueryBuilder('a')
            ->where('a.channel = :channelId')
            ->setParameter('channelId', $channelId);

        $iterator = new BufferedIdentityQueryResultIterator($qb);
        $iterator->setBufferSize(2);

        $this->setSourceIterator($iterator);
    }
}
