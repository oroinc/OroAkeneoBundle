<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Common\Cache\CacheProvider;

trait CacheProviderAwareProcessor
{
    /** @var CacheProvider */
    private $cacheProvider;

    public function setCacheProvider(CacheProvider $cacheProvider)
    {
        $this->cacheProvider = $cacheProvider;
    }
}
