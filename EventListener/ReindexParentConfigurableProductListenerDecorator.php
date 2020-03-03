<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\EventListener\Search\ReindexParentConfigurableProductListener;

/**
 * Product variants lazy processing
 */
class ReindexParentConfigurableProductListenerDecorator implements AdditionalOptionalListenerInterface
{
    use AdditionalOptionalListenerTrait;

    /** @var ReindexParentConfigurableProductListener */
    protected $innerListener;

    public function __construct(ReindexParentConfigurableProductListener $innerListener)
    {
        $this->innerListener = $innerListener;
    }

    public function postPersist(Product $product): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->postPersist($product);
    }

    public function postUpdate(Product $product): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->postUpdate($product);
    }

    public function preRemove(Product $product): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->preRemove($product);
    }
}
