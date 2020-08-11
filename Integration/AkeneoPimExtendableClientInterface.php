<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\MeasurementFamilyApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityRecordApiInterface;

interface AkeneoPimExtendableClientInterface extends AkeneoPimEnterpriseClientInterface
{
    public function getReferenceEntityApi(): ReferenceEntityApiInterface;

    public function setReferenceEntityApi(ReferenceEntityApiInterface $referenceEntityApi): void;

    public function getReferenceEntityRecordApi(): ReferenceEntityRecordApiInterface;

    public function setReferenceEntityRecordApi(ReferenceEntityRecordApiInterface $referenceEntityRecordApi): void;

    public function getMeasurementFamilyApi(): MeasurementFamilyApiInterface;

    public function setMeasurementFamilyApi(MeasurementFamilyApiInterface $measurementFamilyApi): void;
}
