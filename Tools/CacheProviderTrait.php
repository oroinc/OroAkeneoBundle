<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

use Doctrine\Common\Cache\CacheProvider;

trait CacheProviderTrait
{
    /** @var CacheProvider */
    private $cacheProvider;

    public function setCacheProvider(CacheProvider $cacheProvider): void
    {
        $this->cacheProvider = $cacheProvider;
    }
}
