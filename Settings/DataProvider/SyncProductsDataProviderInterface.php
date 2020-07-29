<?php

namespace Oro\Bundle\AkeneoBundle\Settings\DataProvider;

interface SyncProductsDataProviderInterface
{
    /**
     * @return string[]
     */
    public function getSyncProducts();

    public function getDefaultValue(): string;
}
