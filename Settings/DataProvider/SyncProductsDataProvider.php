<?php

namespace Oro\Bundle\AkeneoBundle\Settings\DataProvider;

class SyncProductsDataProvider implements SyncProductsDataProviderInterface
{
    /**
     * @internal
     */
    const PUBLISHED = 'published';

    /**
     * @internal
     */
    const ALL_PRODUCTS = 'all_products';

    /**
     * @return string[]
     */
    public function getSyncProducts()
    {
        return [
            self::ALL_PRODUCTS,
            self::PUBLISHED,
        ];
    }

    public function getDefaultValue(): string
    {
        return self::ALL_PRODUCTS;
    }
}
