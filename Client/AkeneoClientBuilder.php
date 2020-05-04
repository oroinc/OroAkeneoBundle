<?php

namespace Oro\Bundle\AkeneoBundle\Client;

use Akeneo\Pim\ApiClient\Security\Authentication;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientBuilder;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Oro\Bundle\AkeneoBundle\Client\Api\ApiAwareInterface;

class AkeneoClientBuilder extends AkeneoPimEnterpriseClientBuilder
{
    /**
     * @var ApiAwareInterface[]
     */
    protected $apiRegistry = [];

    /**
     * @param ApiAwareInterface|null ...$apis
     */
    public function __construct(?ApiAwareInterface ...$apis)
    {
        foreach ($apis as $api) {
            $this->addApi($api);
        }
    }

    /**
     * @param string $baseUri
     * @return $this
     */
    public function setBaseUri(string $baseUri)
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    public function addApi(ApiAwareInterface $api)
    {
        $shortClass = (new \ReflectionClass($api))->getShortName();
        $this->apiRegistry[$shortClass] = $api;
    }

    /**
     * @inheritDoc
     */
    public function buildAuthenticatedByPassword($clientId, $secret, $username, $password)
    {
        $authentication = Authentication::fromPassword($clientId, $secret, $username, $password);

        return $this->buildAuthenticatedClient($authentication);
    }

    /**
     * @inheritDoc
     */
    protected function buildAuthenticatedClient(Authentication $authentication)
    {
        list($resourceClient, $pageFactory, $cursorFactory, $fileSystem) = parent::setUp($authentication);

        $client = new AkeneoClient(
            parent::buildAuthenticatedClient($authentication)
        );
        foreach ($this->apiRegistry as $key => $api) {
            $api->setResourceClient($resourceClient)
                ->setPageFactory($pageFactory)
                ->setCursorFactory($cursorFactory)
                ->setFileSystem($fileSystem)
            ;
            $client->addApi($key, $api);
        }
        return $client;
    }
}
