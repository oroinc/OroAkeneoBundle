<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedQueryResultIterator;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\Entity\Repository\CategoryRepository;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class CategoryParentReader extends IteratorBasedReader
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

        /** @var CategoryRepository $qb */
        $repo = $this->doctrineHelper->getEntityRepository(Category::class);

        $qb = $repo
            ->createQueryBuilder('c')
            ->where('c.channel = :channelId')
            ->setParameter('channelId', $channelId);

        $iterator = new BufferedQueryResultIterator($qb);
        $iterator->setBufferSize(10);

        $this->setSourceIterator($iterator);
    }
}
