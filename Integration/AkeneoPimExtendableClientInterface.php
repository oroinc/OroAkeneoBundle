<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\MeasurementFamilyApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityAttributeApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityAttributeOptionApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityMediaFileApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityRecordApiInterface;

interface AkeneoPimExtendableClientInterface extends AkeneoPimEnterpriseClientInterface
{
    public function getReferenceEntityApi(): ReferenceEntityApiInterface;

    public function setReferenceEntityApi(ReferenceEntityApiInterface $referenceEntityApi): void;

    public function getReferenceEntityRecordApi(): ReferenceEntityRecordApiInterface;

    public function setReferenceEntityRecordApi(ReferenceEntityRecordApiInterface $referenceEntityRecordApi): void;

    public function getMeasurementFamilyApi(): MeasurementFamilyApiInterface;

    public function setMeasurementFamilyApi(MeasurementFamilyApiInterface $measurementFamilyApi): void;

    public function getReferenceEntityAttributeApi(): ReferenceEntityAttributeApiInterface;

    public function setReferenceEntityAttributeApi(
        ReferenceEntityAttributeApiInterface $referenceEntityAttributeApi
    ): void;

    public function getReferenceEntityAttributeOptionApi(): ReferenceEntityAttributeOptionApiInterface;

    public function setReferenceEntityAttributeOptionApi(
        ReferenceEntityAttributeOptionApiInterface $referenceEntityAttributeOptionApi
    ): void;

    public function getReferenceEntityMediaFileApi(): ReferenceEntityMediaFileApiInterface;

    public function setReferenceEntityMediaFileApi(
        ReferenceEntityMediaFileApiInterface $referenceEntityMediaFileApi
    ): void;
}
