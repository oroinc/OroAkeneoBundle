<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProviderInterface;

class DefaultOwnerHelper
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var OwnershipMetadataProviderInterface */
    private $ownershipMetadataProvider;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        OwnershipMetadataProviderInterface $ownershipMetadataProvider
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->ownershipMetadataProvider = $ownershipMetadataProvider;
    }

    public function populateChannelOwner($entity, Integration $integration)
    {
        $defaultUserOwner = $integration->getDefaultUserOwner();

        $className         = $this->doctrineHelper->getEntityClass($entity);
        $doctrineMetadata  = $this->doctrineHelper->getEntityMetadata($className);
        $ownershipMetadata = $this->ownershipMetadataProvider->getMetadata($className);

        if ($defaultUserOwner && $ownershipMetadata->isUserOwned()) {
            $doctrineMetadata->setFieldValue(
                $entity,
                $ownershipMetadata->getOwnerFieldName(),
                $defaultUserOwner
            );
        }

        if ($defaultUserOwner && $ownershipMetadata->isBusinessUnitOwned()) {
            $doctrineMetadata->setFieldValue(
                $entity,
                $ownershipMetadata->getOwnerFieldName(),
                $defaultUserOwner->getOwner()
            );
        }

        $defaultOrganization = $integration->getOrganization();
        if ($defaultOrganization && $ownershipMetadata->getOrganizationFieldName()) {
            $doctrineMetadata->setFieldValue(
                $entity,
                $ownershipMetadata->getOrganizationFieldName(),
                $defaultOrganization
            );
        }
    }
}
