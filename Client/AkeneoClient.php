<?php

namespace Oro\Bundle\AkeneoBundle\Client;

use Akeneo\Pim\ApiClient\AkeneoPimClientInterface;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Oro\Bundle\AkeneoBundle\Client\Api\ApiAwareInterface;

class AkeneoClient implements AkeneoPimEnterpriseClientInterface
{
    /** @var AkeneoPimEnterpriseClientInterface */
    protected $decoratedClient;

    /** @var ApiAwareInterface[] */
    protected $apiRegistry = [];

    public function __construct(
        AkeneoPimClientInterface $decoratedClient
    ) {
        $this->decoratedClient = $decoratedClient;
    }

    public function addApi(string $key, ApiAwareInterface $api) {
        $this->apiRegistry[$key] = $api;
    }

    public function get(string $name)
    {
        return $this->apiRegistry[$name] ?? null;
    }

    public function __call($name, $arguments)
    {
        $property = lcfirst(substr($name, 3));
        if ('get' === substr($name, 0, 3) && isset($this->apiRegistry[$property])) {
            return $this->apiRegistry[$property];
        }
        return $this->decoratedClient->{$name}($arguments);
    }

    public function getToken()
    {
        return $this->decoratedClient->getToken();
    }

    public function getRefreshToken()
    {
        return $this->decoratedClient->getRefreshToken();
    }

    public function getProductApi()
    {
        return $this->decoratedClient->getProductApi();
    }

    public function getCategoryApi()
    {
        return $this->decoratedClient->getCategoryApi();
    }

    public function getAttributeApi()
    {
        return $this->decoratedClient->getAttributeApi();
    }

    public function getAttributeOptionApi()
    {
        return $this->decoratedClient->getAttributeOptionApi();
    }

    public function getAttributeGroupApi()
    {
        return $this->decoratedClient->getAttributeGroupApi();
    }

    public function getFamilyApi()
    {
        return $this->decoratedClient->getFamilyApi();
    }

    public function getProductMediaFileApi()
    {
        return $this->decoratedClient->getProductMediaFileApi();
    }

    public function getLocaleApi()
    {
        return $this->decoratedClient->getLocaleApi();
    }

    public function getChannelApi()
    {
        return $this->decoratedClient->getChannelApi();
    }

    public function getCurrencyApi()
    {
        return $this->decoratedClient->getCurrencyApi();
    }

    public function getMeasureFamilyApi()
    {
        return $this->decoratedClient->getMeasureFamilyApi();
    }

    public function getAssociationTypeApi()
    {
        return $this->decoratedClient->getAssociationTypeApi();
    }

    public function getFamilyVariantApi()
    {
        return $this->decoratedClient->getFamilyVariantApi();
    }

    public function getProductModelApi()
    {
        return $this->decoratedClient->getProductModelApi();
    }

    public function getPublishedProductApi()
    {
        return $this->decoratedClient->getPublishedProductApi();
    }

    public function getProductModelDraftApi()
    {
        return $this->decoratedClient->getProductModelDraftApi();
    }

    public function getProductDraftApi()
    {
        return $this->decoratedClient->getProductDraftApi();
    }

    public function getAssetApi()
    {
        return $this->decoratedClient->getAssetApi();
    }

    public function getAssetCategoryApi()
    {
        return $this->decoratedClient->getAssetCategoryApi();
    }

    public function getAssetTagApi()
    {
        return $this->decoratedClient->getAssetTagApi();
    }

    public function getAssetReferenceFileApi()
    {
        return $this->decoratedClient->getAssetReferenceFileApi();
    }

    public function getAssetVariationFileApi()
    {
        return $this->decoratedClient->getAssetVariationFileApi();
    }
}
