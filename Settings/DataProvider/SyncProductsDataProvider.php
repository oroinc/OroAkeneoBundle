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
            self::PUBLISHED,
            self::ALL_PRODUCTS,
        ];
    }
}
