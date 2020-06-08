<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;

class AttributesDatagridListener
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    public function onBuildBefore(BuildBefore $event)
    {
        $event->getConfig()->offsetSetByPath(
            '[columns][attributeFamilies][template]',
            'OroAkeneoBundle:Datagrid:attributeFamilies.html.twig'
        );
    }

    public function onResultAfter(OrmResultAfter $event)
    {
        $attributeIds = [];
        /** @var ResultRecord[] $records */
        $records = $event->getRecords();
        foreach ($records as $record) {
            $attributeIds[] = $record->getValue('id');
        }

        $families = $this->getFamiliesLabelsByAttributeIds($attributeIds);

        foreach ($records as $record) {
            $record->setValue('attributeFamilies', $families[$record->getValue('id')] ?? []);
        }
    }

    private function getFamiliesLabelsByAttributeIds(array $attributeIds): array
    {
        if (empty($attributeIds)) {
            return [];
        }

        $qb = $this->doctrineHelper
            ->getEntityRepository(AttributeGroupRelation::class)
            ->createQueryBuilder('attributeGroupRelation');

        $qb
            ->select('attributeGroupRelation.entityConfigFieldId, attributeFamily.id, labels.string')
            ->innerJoin('attributeGroupRelation.attributeGroup', 'attributeGroup')
            ->innerJoin('attributeGroup.attributeFamily', 'attributeFamily')
            ->innerJoin('attributeFamily.labels', 'labels', Join::WITH, $qb->expr()->isNull('labels.localization'))
            ->where($qb->expr()->in('attributeGroupRelation.entityConfigFieldId', ':ids'))
            ->setParameter('ids', $attributeIds)
            ->orderBy($qb->expr()->asc('attributeFamily.id'))
            ->getQuery()
            ->getResult();

        $results = $qb->getQuery()->getResult();

        $families = [];
        foreach ($results as $result) {
            $families[$result['entityConfigFieldId']][$result['id']] = $result['string'];
        }

        return $families;
    }
}
