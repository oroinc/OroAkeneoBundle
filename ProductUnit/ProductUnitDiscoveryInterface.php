<?php

namespace Oro\Bundle\AkeneoBundle\ProductUnit;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Exceptions\IgnoreProductUnitChangesException;

interface ProductUnitDiscoveryInterface
{
    /**
     * @throws IgnoreProductUnitChangesException
     */
    public function discover(AkeneoSettings $transport, array $importedRecord): array;
}
