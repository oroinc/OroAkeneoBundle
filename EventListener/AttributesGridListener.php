<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * Shows only attributes that related to the current organization or Global or System
 */
class AttributesGridListener
{
    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function __construct(TokenAccessorInterface $tokenAccessor, DoctrineHelper $doctrineHelper)
    {
        $this->tokenAccessor = $tokenAccessor;
        $this->doctrineHelper = $doctrineHelper;
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $datasource = $datagrid->getDatasource();
        if ($datasource instanceof OrmDatasource) {
            $organization = $this->tokenAccessor->getOrganization();
            if ($organization && $organization->getIsGlobal() === false) {
                $datasource->getQueryBuilder()
                    ->andWhere(
                        'cfv_attribute_is_global.value =:isGlobal' .
                        ' OR cfv_extend_owner.value = :isSystem' .
                        ' OR cfv_attribute_organization_id.value = :organization'
                    )
                    ->setParameter('isGlobal', '1')
                    ->setParameter('isSystem', ExtendScope::OWNER_SYSTEM)
                    ->setParameter('organization', (string)$organization->getId());
            }
        }
    }

    public function onResultAfter(OrmResultAfter $event): void
    {
        $organization = $this->tokenAccessor->getOrganization();
        if ($organization && $organization->getIsGlobal() === false) {
            /** @var ResultRecord[] $records */
            $records = $event->getRecords();

            foreach ($records as $record) {
                $familyIds = array_keys($record->getValue('attributeFamilies'));
                $ownerIds = $this->getOwnerIds($familyIds);

                $attributeFamilies = array_filter(
                    $record->getValue('attributeFamilies'),
                    static function (string $familyName, int $familyId) use ($organization, $ownerIds) {
                        return ($ownerIds[$familyId] ?? null) === $organization->getId();
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                $record->setValue('attributeFamilies', $attributeFamilies);
            }
        }
    }

    private function getOwnerIds(array $familyIds): array
    {
        if (!$familyIds) {
            return [];
        }

        $qb = $this->doctrineHelper->createQueryBuilder(AttributeFamily::class, 'f');
        $qb
            ->select('f.id, IDENTITY(f.owner) as owner')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $familyIds);

        $owners = [];
        foreach ($qb->getQuery()->getResult() as $item) {
            $owners[$item['id']] = $item['owner'];
        }

        return $owners;
    }
}
