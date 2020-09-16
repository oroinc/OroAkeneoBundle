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
    public const TWO_LEVEL_FAMILY_VARIANT_FIRST_ONLY = 'first_only';
    public const TWO_LEVEL_FAMILY_VARIANT_SECOND_ONLY = 'second_only';
    public const TWO_LEVEL_FAMILY_VARIANT_BOTH = 'both';
    public const DEFAULT_ATTRIBUTES_MAPPING = 'name:names;description:descriptions;';
    public const DEFAULT_BRAND_MAPPING = 'label:names';

    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_sync_products", type="string", length=255, nullable=false)
     */
    protected $syncProducts;
    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_product_unit_attribute", type="string", length=255, nullable=true)
     */
    protected $productUnitAttribute;
    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_unit_precision_attr", type="string", length=255, nullable=true)
     */
    protected $productUnitPrecisionAttribute;
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
    private $akeneoAttributesList;

    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_attributes_image_list", type="text", nullable=true)
     */
    private $akeneoAttributesImageList;

    /**
     * @var boolean
     *
     * @ORM\Column(name="akeneo_merge_image_to_parent", type="boolean", options={"default"=false})
     */
    private $akeneoMergeImageToParent = false;

    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_variant_levels", type="string", length=255)
     */
    private $akeneoVariantLevels;

    /**
     * @var string
     *
     * @ORM\Column(name="akeneo_attributes_mapping", type="text", nullable=true)
     */
    private $akeneoAttributesMapping;

    /**
     * @ORM\Column(name="akeneo_brand_reference_code", type="string", length=255)
     */
    private $akeneoBrandReferenceEntityCode;

    /**
     * @ORM\Column(name="akeneo_brand_mapping", type="text", nullable=true)
     */
    private $akeneoBrandMapping;

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
     * @return $this
     */
    public function addAkeneoLocale(AkeneoLocale $akeneoLocale)
    {
        $this->akeneoLocales[] = $akeneoLocale;
        $akeneoLocale->setAkeneoSettings($this);

        return $this;
    }

    /**
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
                    'productUnitAttribute' => $this->getProductUnitAttribute(),
                    'productUnitPrecisionAttribute' => $this->getProductUnitPrecisionAttribute(),
                    'akeneoCurrencies' => $this->getAkeneoCurrencies(),
                    'akeneoActiveCurrencies' => $this->getAkeneoActiveCurrencies(),
                    'akeneoLocales' => $this->getAkeneoLocales()->toArray(),
                    'akeneoLocalesList' => $this->getAkeneoLocalesList(),
                    'akeneoAttributesList' => $this->getAkeneoAttributesList(),
                    'akeneoVariantLevels' => $this->getAkeneoVariantLevels(),
                    'akeneoAttributesMapping' => $this->getAkeneoAttributesMapping(),
                    'akeneoBrandReferenceEntityCode' => $this->getAkeneoBrandReferenceEntityCode(),
                    'akeneoBrandMapping' => $this->getAkeneoBrandMapping(),
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
     * @return string
     */
    public function getProductUnitAttribute()
    {
        return $this->productUnitAttribute;
    }

    /**
     * @param string $productUnitAttribute
     */
    public function setProductUnitAttribute($productUnitAttribute)
    {
        $this->productUnitAttribute = $productUnitAttribute;

        return $this;
    }

    /**
     * @return string
     */
    public function getProductUnitPrecisionAttribute()
    {
        return $this->productUnitPrecisionAttribute;
    }

    /**
     * @param string $productUnitPrecisionAttribute
     */
    public function setProductUnitPrecisionAttribute($productUnitPrecisionAttribute)
    {
        $this->productUnitPrecisionAttribute = $productUnitPrecisionAttribute;

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
     * @return self
     */
    public function setAkeneoLocalesList(array $akeneoLocalesList = null)
    {
        $this->akeneoLocalesList = $akeneoLocalesList;

        return $this;
    }

    public function getAkeneoAttributesImageList(): ?string
    {
        return $this->akeneoAttributesImageList;
    }

    /**
     * @param string $akeneoAttributesImageList
     */
    public function setAkeneoAttributesImageList(string $akeneoAttributesImageList = null): self
    {
        $this->akeneoAttributesImageList = $akeneoAttributesImageList;

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
     * @return AkeneoSettings
     */
    public function setPriceList(PriceList $priceList): self
    {
        $this->priceList = $priceList;

        return $this;
    }

    public function getAkeneoAttributesList(): ?string
    {
        return $this->akeneoAttributesList;
    }

    /**
     * @param string $attributeList
     */
    public function setAkeneoAttributesList(string $attributeList = null): self
    {
        $this->akeneoAttributesList = $attributeList;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAkeneoMergeImageToParent(): ?bool
    {
        return $this->akeneoMergeImageToParent;
    }

    /**
     * @return $this
     */
    public function setAkeneoMergeImageToParent(bool $akeneoMergeImageToParent)
    {
        $this->akeneoMergeImageToParent = $akeneoMergeImageToParent;

        return $this;
    }

    public function getAkeneoVariantLevels(): ?string
    {
        return $this->akeneoVariantLevels;
    }

    public function setAkeneoVariantLevels(string $akeneoVariantLevels): self
    {
        $this->akeneoVariantLevels = $akeneoVariantLevels;

        return $this;
    }

    public function getAkeneoAttributesMapping(): ?string
    {
        return $this->akeneoAttributesMapping;
    }

    public function setAkeneoAttributesMapping(string $akeneoAttributesMapping): self
    {
        $this->akeneoAttributesMapping = $akeneoAttributesMapping;

        return $this;
    }

    public function getAkeneoBrandReferenceEntityCode(): ?string
    {
        return $this->akeneoBrandReferenceEntityCode;
    }

    public function setAkeneoBrandReferenceEntityCode(?string $akeneoBrandReferenceEntityCode): void
    {
        $this->akeneoBrandReferenceEntityCode = $akeneoBrandReferenceEntityCode;
    }

    public function getAkeneoBrandMapping(): ?string
    {
        return $this->akeneoBrandMapping;
    }

    public function setAkeneoBrandMapping(?string $akeneoBrandMapping): void
    {
        $this->akeneoBrandMapping = $akeneoBrandMapping;
    }
}
