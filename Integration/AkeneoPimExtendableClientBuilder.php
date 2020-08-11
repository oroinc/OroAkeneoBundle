<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Akeneo\Pim\ApiClient\Security\Authentication;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientBuilder;
use Oro\Bundle\AkeneoBundle\Integration\Api\MeasurementFamilyApi;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityApi;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityRecordApi;

class AkeneoPimExtendableClientBuilder extends AkeneoPimEnterpriseClientBuilder
{
    private $deps = [];

    protected function buildAuthenticatedClient(Authentication $authentication)
    {
        $client = new AkeneoPimExtendableClient($authentication, parent::buildAuthenticatedClient($authentication));

        list($resourceClient, $pageFactory, $cursorFactory, $fileSystem) = $this->setUp($authentication);
        $client->setReferenceEntityApi(new ReferenceEntityApi($resourceClient, $pageFactory, $cursorFactory));
        $client->setReferenceEntityRecordApi(
            new ReferenceEntityRecordApi($resourceClient, $pageFactory, $cursorFactory)
        );
        $client->setMeasurementFamilyApi(new MeasurementFamilyApi($resourceClient));

        return $client;
    }

    protected function setUp(Authentication $authentication)
    {
        if ($this->deps) {
            return $this->deps;
        }

        $this->deps = parent::setUp($authentication);

        return $this->deps;
    }
}
