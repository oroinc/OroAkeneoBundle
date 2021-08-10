<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class AsyncProcessor implements ProcessorInterface
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $variants = [];

    public function process($item)
    {
        $this->updateVariants($item);

        return $item;
    }

    private function updateVariants(array &$item)
    {
        $sku = $item['sku'];

        if (!empty($item['family_variant'])) {
            if (isset($item['parent'], $this->variants[$sku])) {
                $parent = $item['parent'];
                foreach (array_keys($this->variants[$sku]) as $sku) {
                    $this->variants[$parent][$sku] = ['parent' => $parent, 'variant' => $sku];
                }
            }

            return;
        }

        if (empty($item['parent'])) {
            return;
        }

        $parent = $item['parent'];

        $this->variants[$parent][$sku] = ['parent' => $parent, 'variant' => $sku];
    }

    public function initialize()
    {
        $this->variants = [];
        $this->cacheProvider->delete('product_variants');
    }

    public function flush()
    {
        $this->cacheProvider->save('product_variants', $this->variants);
        $this->variants = [];
    }
}
