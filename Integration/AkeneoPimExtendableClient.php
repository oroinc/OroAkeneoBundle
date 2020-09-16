<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\Pim\ApiClient\Security\Authentication;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\MeasurementFamilyApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityAttributeApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityAttributeOptionApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityMediaFileApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityRecordApiInterface;

class AkeneoPimExtendableClient implements AkeneoPimExtendableClientInterface
{
    /** @var Authentication */
    private $authentication;

    /** @var AkeneoPimEnterpriseClientInterface */
    private $client;

    /** @var ReferenceEntityApiInterface */
    private $referenceEntityApi;

    /** @var ReferenceEntityRecordApiInterface */
    private $referenceEntityRecordApi;

    /** @var ReferenceEntityAttributeApiInterface */
    private $referenceEntityAttributeApi;

    /** @var ReferenceEntityAttributeOptionApiInterface */
    private $referenceEntityAttributeOptionApi;

    /** @var ReferenceEntityMediaFileApiInterface */
    private $referenceEntityMediaFileApi;

    /** @var MeasurementFamilyApiInterface */
    private $measurementFamilyApi;

    public function __construct(Authentication $authentication, AkeneoPimEnterpriseClientInterface $client)
    {
        $this->authentication = $authentication;
        $this->client = $client;
    }

    public function getToken()
    {
        return $this->client->getToken();
    }

    public function getRefreshToken()
    {
        return $this->client->getRefreshToken();
    }

    public function getProductApi()
    {
        return $this->client->getProductApi();
    }

    public function getCategoryApi()
    {
        return $this->client->getCategoryApi();
    }

    public function getAttributeApi()
    {
        return $this->client->getAttributeApi();
    }

    public function getAttributeOptionApi()
    {
        return $this->client->getAttributeOptionApi();
    }

    public function getAttributeGroupApi()
    {
        return $this->client->getAttributeGroupApi();
    }

    public function getFamilyApi()
    {
        return $this->client->getFamilyApi();
    }

    public function getProductMediaFileApi()
    {
        return $this->client->getProductMediaFileApi();
    }

    public function getLocaleApi()
    {
        return $this->client->getLocaleApi();
    }

    public function getChannelApi()
    {
        return $this->client->getChannelApi();
    }

    public function getCurrencyApi()
    {
        return $this->client->getCurrencyApi();
    }

    public function getMeasureFamilyApi()
    {
        return $this->client->getMeasureFamilyApi();
    }

    public function getAssociationTypeApi()
    {
        return $this->client->getAssociationTypeApi();
    }

    public function getFamilyVariantApi()
    {
        return $this->client->getFamilyVariantApi();
    }

    public function getProductModelApi()
    {
        return $this->client->getProductModelApi();
    }

    public function getPublishedProductApi()
    {
        return $this->client->getPublishedProductApi();
    }

    public function getProductModelDraftApi()
    {
        return $this->client->getProductModelDraftApi();
    }

    public function getProductDraftApi()
    {
        return $this->client->getProductDraftApi();
    }

    public function getAssetApi()
    {
        return $this->client->getAssetApi();
    }

    public function getAssetCategoryApi()
    {
        return $this->client->getAssetCategoryApi();
    }

    public function getAssetTagApi()
    {
        return $this->client->getAssetTagApi();
    }

    public function getAssetReferenceFileApi()
    {
        return $this->client->getAssetReferenceFileApi();
    }

    public function getAssetVariationFileApi()
    {
        return $this->client->getAssetVariationFileApi();
    }

    public function getReferenceEntityApi(): ReferenceEntityApiInterface
    {
        return $this->referenceEntityApi;
    }

    public function setReferenceEntityApi(ReferenceEntityApiInterface $referenceEntityApi): void
    {
        $this->referenceEntityApi = $referenceEntityApi;
    }

    public function getReferenceEntityRecordApi(): ReferenceEntityRecordApiInterface
    {
        return $this->referenceEntityRecordApi;
    }

    public function setReferenceEntityRecordApi(ReferenceEntityRecordApiInterface $referenceEntityRecordApi): void
    {
        $this->referenceEntityRecordApi = $referenceEntityRecordApi;
    }

    public function getMeasurementFamilyApi(): MeasurementFamilyApiInterface
    {
        return $this->measurementFamilyApi;
    }

    public function setMeasurementFamilyApi(MeasurementFamilyApiInterface $measurementFamilyApi): void
    {
        $this->measurementFamilyApi = $measurementFamilyApi;
    }

    public function getReferenceEntityAttributeApi(): ReferenceEntityAttributeApiInterface
    {
        return $this->referenceEntityAttributeApi;
    }

    public function setReferenceEntityAttributeApi(
        ReferenceEntityAttributeApiInterface $referenceEntityAttributeApi
    ): void {
        $this->referenceEntityAttributeApi = $referenceEntityAttributeApi;
    }

    public function getReferenceEntityAttributeOptionApi(): ReferenceEntityAttributeOptionApiInterface
    {
        return $this->referenceEntityAttributeOptionApi;
    }

    public function setReferenceEntityAttributeOptionApi(
        ReferenceEntityAttributeOptionApiInterface $referenceEntityAttributeOptionApi
    ): void {
        $this->referenceEntityAttributeOptionApi = $referenceEntityAttributeOptionApi;
    }

    public function getReferenceEntityMediaFileApi(): ReferenceEntityMediaFileApiInterface
    {
        return $this->referenceEntityMediaFileApi;
    }

    public function setReferenceEntityMediaFileApi(
        ReferenceEntityMediaFileApiInterface $referenceEntityMediaFileApi
    ): void {
        $this->referenceEntityMediaFileApi = $referenceEntityMediaFileApi;
    }
}
