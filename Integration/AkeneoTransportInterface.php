<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Oro\Bundle\IntegrationBundle\Provider\TransportInterface;
use Oro\Bundle\MultiCurrencyBundle\Config\MultiCurrencyConfigProvider;

interface AkeneoTransportInterface extends TransportInterface
{
    /**
     * @return array
     */
    public function getCurrencies();

    /**
     * @return array
     */
    public function getMergedCurrencies();

    public function setConfigProvider(MultiCurrencyConfigProvider $configProvider);

    /**
     * @return array
     */
    public function getLocales();

    /**
     * @return array
     */
    public function getChannels();

    /**
     * @return \Iterator
     */
    public function getCategories(int $pageSize);

    /**
     * @return \Iterator
     */
    public function getAttributeFamilies();

    /**
     * {@inheritdoc}
     *
     * @return \Iterator
     */
    public function getProducts(int $pageSize, ?\DateTime $updatedAt = null);

    /**
     * @return \Iterator
     */
    public function getProductModels(int $pageSize, ?\DateTime $updatedAt = null);

    /**
     * @return \Iterator
     */
    public function getAttributes(int $pageSize);
}
