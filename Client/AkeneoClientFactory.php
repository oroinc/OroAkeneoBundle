<?php

namespace Oro\Bundle\AkeneoBundle\Client;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClient;
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
     * @var AkeneoClientBuilder
     */
    private $clientBuilder;

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
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        Crypter $crypter,
        AkeneoClientBuilder $clientBuilder
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->crypter = $crypter;
        $this->clientBuilder = $clientBuilder;
    }

    /**
     * Create Akeneo PIM client instance.
     *
     * @param bool $tokensEnabled
     *
     * @return AkeneoPimEnterpriseClient
     */
    public function getInstance(AkeneoSettings $akeneoSettings, $tokensEnabled = true)
    {
        $this->initProperties($akeneoSettings);

        if (
            $tokensEnabled &&
            $akeneoSettings->getToken() &&
            $akeneoSettings->getTokenExpiryDateTime() &&
            $akeneoSettings->getTokenExpiryDateTime() > new \DateTime('now', new \DateTimeZone('UTC'))
        ) {
            $this->createClientByToken();
        } else {
            $this->createClient();
        }

        return $this->client;
    }

    /**
     * Set properties from AkeneoSettings entity.
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
        $this->clientBuilder->setBaseUri($this->akeneoUrl);
        $this->client = $this->clientBuilder->buildAuthenticatedByToken(
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
        $this->clientBuilder->setBaseUri($this->akeneoUrl);
        $this->client = $this->clientBuilder->buildAuthenticatedByPassword(
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
        $em = $this->doctrineHelper->getEntityManager($this->akeneoSettings);
        $em->getUnitOfWork()->scheduleExtraUpdate(
            $this->akeneoSettings,
            [
                'token' => [null, $this->client->getToken()],
                'refreshToken' => [null, $this->client->getRefreshToken()],
                'tokenExpiryDateTime' => [null, new \DateTime('now +3500 seconds')],
            ]
        );
        $em->flush();
    }
}
