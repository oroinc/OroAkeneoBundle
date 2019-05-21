<?php

namespace Oro\Bundle\AkeneoBundle\Client;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClient;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientBuilder;
use Oro\Bundle\AkeneoBundle\Encoder\Crypter;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

/**
 * Factory to create Akeneo PIM client instance.
 */
class AkeneoClientFactory
{
    /** @deprecated */
    const MASTER_CHANNEL_NAME = 'master';

    /**
     * @var Crypter
     */
    private $crypter;

    /**
     * @var string
     */
    private $akeneoUrl;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $userName;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var AkeneoPimEnterpriseClient
     */
    private $client;

    /**
     * @var AkeneoSettings
     */
    private $akeneoSettings;

    /**
     * @var DoctrineHelper
     */
    private $doctrineHelper;

    /**
     * AkeneoClientFactory constructor.
     *
     * @param DoctrineHelper $doctrineHelper
     * @param Crypter $crypter
     */
    public function __construct(DoctrineHelper $doctrineHelper, Crypter $crypter)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->crypter = $crypter;
    }

    /**
     * Create Akeneo PIM client instance.
     *
     * @param AkeneoSettings $akeneoSettings
     * @param bool $tokensEnabled
     *
     * @return AkeneoPimEnterpriseClient
     */
    public function getInstance(AkeneoSettings $akeneoSettings, $tokensEnabled = true)
    {
        $this->initProperties($akeneoSettings);

        if ($akeneoSettings->getToken() &&
            $akeneoSettings->getTokenExpiryDateTime() &&
            $akeneoSettings->getTokenExpiryDateTime() > new \DateTime('now') &&
            true === $tokensEnabled
        ) {
            $this->createClientByToken();
        } else {
            $this->createClient();
        }

        return $this->client;
    }

    /**
     * Set properties from AkeneoSettings entity.
     *
     * @param AkeneoSettings $akeneoSettings
     */
    private function initProperties(AkeneoSettings $akeneoSettings)
    {
        $this->akeneoSettings = $akeneoSettings;
        $this->akeneoUrl = $akeneoSettings->getUrl();
        $this->clientId = $akeneoSettings->getClientId();
        $this->secret = $akeneoSettings->getSecret();
        $this->userName = $akeneoSettings->getUsername();
        $this->password = $this->crypter->getDecryptData($akeneoSettings->getPassword());
        $this->token = $akeneoSettings->getToken();
        $this->refreshToken = $akeneoSettings->getRefreshToken();
    }

    /**
     * Build client by token.
     *
     * @return AkeneoPimEnterpriseClient
     */
    private function createClientByToken()
    {
        $clientBuilder = new AkeneoPimEnterpriseClientBuilder($this->akeneoUrl);
        $this->client = $clientBuilder->buildAuthenticatedByToken(
            $this->clientId,
            $this->secret,
            $this->token,
            $this->refreshToken
        );

        return $this->client;
    }

    /**
     * Build token by username and password.
     *
     * @return AkeneoPimEnterpriseClient
     */
    private function createClient()
    {
        $clientBuilder = new AkeneoPimEnterpriseClientBuilder($this->akeneoUrl);
        $this->client = $clientBuilder->buildAuthenticatedByPassword(
            $this->clientId,
            $this->secret,
            $this->userName,
            $this->password
        );

        if ($this->akeneoSettings->getId()) {
            $this->persistTokens();
        }

        return $this->client;
    }

    /**
     * Persist authentication tokens.
     * Sends request to get currencies. It's needed to fetch token.
     */
    private function persistTokens()
    {
        $this->client->getCurrencyApi()->all();
        $this->akeneoSettings->setToken($this->client->getToken());
        $this->akeneoSettings->setRefreshToken($this->client->getRefreshToken());
        $this->akeneoSettings->setTokenExpiryDateTime(new \DateTime('now +3590 seconds'));
        $em = $this->doctrineHelper->getEntityManager($this->akeneoSettings);
        $em->persist($this->akeneoSettings);
        $em->flush();
    }
}
