<?php

namespace Oro\Bundle\AkeneoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="oro_akeneo_locale")
 *
 * @ORM\Entity(repositoryClass="Oro\Bundle\AkeneoBundle\Entity\Repository\AkeneoLocaleRepository")
 */
class AkeneoLocale
{
    /**
     * @var string
     *
     * @ORM\Column(name="locale", type="string", length=10, nullable=true)
     */
    protected $locale;
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=200)
     */
    private $code;
    /**
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings", inversedBy="akeneoLocales")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $akeneoSettings;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return AkeneoLocale
     */
    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale = null): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get $akeneoSettings.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAkeneoSettings()
    {
        return $this->akeneoSettings;
    }

    /**
     * @return AkeneoLocale
     */
    public function setAkeneoSettings(AkeneoSettings $akeneoSettings = null): self
    {
        $this->akeneoSettings = $akeneoSettings;

        return $this;
    }
}
