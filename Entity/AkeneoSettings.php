<?php

namespace Oro\Bundle\AkeneoBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @ORM\Entity(repositoryClass="Oro\Bundle\AkeneoBundle\Entity\Repository\AkeneoSettingsRepository")
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class AkeneoSettings extends Transport
{
    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_sync_products", type="string", length=255, nullable=false)
     */
    protected $syncProducts;
    /**
     * @var string[]
     *
     * @ORM\Column(name="akeneo_channels", type="array", nullable=true)
     */
    protected $akeneoChannels;
    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_active_channel", type="string", nullable=true)
     */
    protected $akeneoActiveChannel;
    /**
     * @var string[]
     *
     * @ORM\Column(name="akeneo_currencies", type="array", nullable=true)
     */
    protected $akeneoCurrencies;
    /**
     * @var string[]
     *
     * @ORM\Column(name="akeneo_active_currencies", type="array", nullable=true)
     */
    protected $akeneoActiveCurrencies;
    /**
     * @var string[]
     *
     * @ORM\Column(name="akeneo_locales_list", type="array", nullable=true)
     */
    protected $akeneoLocalesList;
    /**
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CatalogBundle\Entity\Category")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $rootCategory;
    /**
     * @var bool
     *
     * @ORM\Column(name="akeneo_acl_voter_enabled", type="boolean")
     */
    protected $aclVoterEnabled = true;
    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_product_filter", type="text", nullable=true)
     */
    protected $productFilter;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_url", length=100)
     */
    private $url;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_client_id", length=100)
     */
    private $clientId;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_secret", length=100)
     */
    private $secret;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_username", length=200)
     */
    private $username;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_password", length=200)
     */
    private $password;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_token", length=200)
     */
    private $token;
    /**
     * @var string
     *
     * @ORM\Column(type="string", name="akeneo_refresh_token", length=200)
     */
    private $refreshToken;

    /**
     * @var string
     *
     * @ORM\Column(type="datetime", name="akeneo_token_expiry_date_time", nullable=true)
     */
    private $tokenExpiryDateTime;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Oro\Bundle\AkeneoBundle\Entity\AkeneoLocale",
     *     mappedBy="akeneoSettings",
     *     cascade={"persist"},
     *     orphanRemoval=true,
     *     fetch="EAGER"
     * )
     */
    private $akeneoLocales;

    /**
     * @var PriceList
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\PricingBundle\Entity\PriceList")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private $priceList;

    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_attributes_list", type="text", nullable=true)
     */
    private $attributesList;

    /**
     * @var ParameterBag
     */
    private $settings;

    public function __construct()
    {
        $this->akeneoLocales = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return AkeneoSettings
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param AkeneoLocale $akeneoLocale
     *
     * @return $this
     */
    public function addAkeneoLocale(AkeneoLocale $akeneoLocale)
    {
        $this->akeneoLocales[] = $akeneoLocale;
        $akeneoLocale->setAkeneoSettings($this);

        return $this;
    }

    /**
     * @param AkeneoLocale $akeneoLocale
     *
     * @return $this
     */
    public function removeAkeneoLocale(AkeneoLocale $akeneoLocale)
    {
        $this->akeneoLocales->removeElement($akeneoLocale);
        $akeneoLocale->setAkeneoSettings(null);

        return $this;
    }

    /**
     * @return string
     */
    public function getProductFilter()
    {
        return $this->productFilter;
    }

    /**
     * @param string $productFilter
     *
     * @return self
     */
    public function setProductFilter($productFilter)
    {
        $this->productFilter = $productFilter;

        return $this;
    }

    /**
     * @return ParameterBag
     */
    public function getSettingsBag()
    {
        if (null === $this->settings) {
            $this->settings = new ParameterBag(
                [
                    'clientId' => $this->getClientId(),
                    'secret' => $this->getSecret(),
                    'akeneoChannels' => $this->getAkeneoChannels(),
                    'akeneoActiveChannel' => $this->getAkeneoActiveChannel(),
                    'username' => $this->getUsername(),
                    'password' => $this->getPassword(),
                    'token' => $this->getToken(),
                    'refreshToken' => $this->getRefreshToken(),
                    'syncProducts' => $this->getSyncProducts(),
                    'akeneoCurrencies' => $this->getAkeneoCurrencies(),
                    'akeneoActiveCurrencies' => $this->getAkeneoActiveCurrencies(),
                    'akeneoLocales' => $this->getAkeneoLocales()->toArray(),
                    'akeneoLocalesList' => $this->getAkeneoLocalesList(),
                    'akeneoAttributesList' => $this->getAttributesList(),
                ]
            );
        }

        return $this->settings;
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     *
     * @return AkeneoSettings
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return AkeneoSettings
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Gets akeneoChannels.
     *
     * @return array|null
     */
    public function getAkeneoChannels()
    {
        return $this->akeneoChannels;
    }

    /**
     * Sets akeneoChannels.
     *
     * @param array|null $akeneoChannels
     *
     * @return self
     */
    public function setAkeneoChannels(array $akeneoChannels = null)
    {
        $this->akeneoChannels = $akeneoChannels;

        return $this;
    }

    /**
     * Gets akeneoActiveChannel.
     *
     * @return string|null
     */
    public function getAkeneoActiveChannel()
    {
        return $this->akeneoActiveChannel;
    }

    /**
     * Sets akeneoActiveChannel.
     *
     * @param string|null $akeneoActiveChannel
     *
     * @return self
     */
    public function setAkeneoActiveChannel($akeneoActiveChannel = null)
    {
        $this->akeneoActiveChannel = $akeneoActiveChannel;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return AkeneoSettings
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return AkeneoSettings
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * @param string $refreshToken
     */
    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * Get syncProducts.
     *
     * @return string
     */
    public function getSyncProducts()
    {
        return $this->syncProducts;
    }

    /**
     * Set syncProducts.
     *
     * @param string $syncProducts
     *
     * @return AkeneoSettings
     */
    public function setSyncProducts($syncProducts)
    {
        $this->syncProducts = $syncProducts;

        return $this;
    }

    /**
     * @return array/null
     */
    public function getAkeneoCurrencies()
    {
        return $this->akeneoCurrencies;
    }

    /**
     * Sets akeneoCurrencies.
     *
     * @param array|null $akeneoCurrencies
     *
     * @return self
     */
    public function setAkeneoCurrencies(array $akeneoCurrencies = null)
    {
        $this->akeneoCurrencies = $akeneoCurrencies;

        return $this;
    }

    /**
     * Gets akeneoActiveCurrencies.
     *
     * @return array|null
     */
    public function getAkeneoActiveCurrencies()
    {
        return $this->akeneoActiveCurrencies;
    }

    /**
     * Sets akeneoActiveCurrencies.
     *
     * @param array|null $akeneoActiveCurrencies
     *
     * @return self
     */
    public function setAkeneoActiveCurrencies(array $akeneoActiveCurrencies = null)
    {
        $this->akeneoActiveCurrencies = $akeneoActiveCurrencies;

        return $this;
    }

    /**
     * @return Collection|AkeneoLocale[]
     */
    public function getAkeneoLocales()
    {
        return $this->akeneoLocales;
    }

    /**
     * Gets akeneoLocalesList.
     *
     * @return array|null
     */
    public function getAkeneoLocalesList()
    {
        return $this->akeneoLocalesList;
    }

    /**
     * Sets akeneoLocalesList.
     *
     * @param array|null $akeneoLocalesList
     *
     * @return self
     */
    public function setAkeneoLocalesList(array $akeneoLocalesList = null)
    {
        $this->akeneoLocalesList = $akeneoLocalesList;

        return $this;
    }

    /**
     * @return string
     */
    public function getTokenExpiryDateTime()
    {
        return $this->tokenExpiryDateTime;
    }

    /**
     * @param DateTime $tokenExpiryDateTime
     *
     * @return AkeneoSettings
     */
    public function setTokenExpiryDateTime(DateTime $tokenExpiryDateTime): self
    {
        $this->tokenExpiryDateTime = $tokenExpiryDateTime;

        return $this;
    }

    /**
     * Get root category.
     *
     * @return Category|null
     */
    public function getRootCategory()
    {
        return $this->rootCategory;
    }

    /**
     * Set root category.
     *
     * @param mixed $rootCategory
     *
     * @return AkeneoSettings
     */
    public function setRootCategory(Category $rootCategory = null)
    {
        $this->rootCategory = $rootCategory;

        return $this;
    }

    /**
     * Get mapped locale.
     *
     * @param $locale
     *
     * @return null|string
     */
    public function getMappedAkeneoLocale(string $locale)
    {
        foreach ($this->getAkeneoLocales() as $akeneoLocale) {
            if ($akeneoLocale->getLocale() === $locale) {
                return $akeneoLocale->getCode();
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function isAclVoterEnabled()
    {
        return $this->aclVoterEnabled;
    }

    /**
     * @param bool $aclVoterEnabled
     *
     * @return AkeneoSettings
     */
    public function setAclVoterEnabled($aclVoterEnabled)
    {
        $this->aclVoterEnabled = $aclVoterEnabled;

        return $this;
    }

    /**
     * @return PriceList
     */
    public function getPriceList(): ?PriceList
    {
        return $this->priceList;
    }

    /**
     * @param PriceList $priceList
     *
     * @return AkeneoSettings
     */
    public function setPriceList(PriceList $priceList): self
    {
        $this->priceList = $priceList;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAttributesList(): ?string
    {
        return $this->attributesList;
    }

    /**
     * @param string $attributeList
     *
     * @return $this
     */
    public function setAttributesList($attributeList)
    {
        $this->attributesList = $attributeList;

        return $this;
    }
}
