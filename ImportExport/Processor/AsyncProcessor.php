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
        if (empty($item['parent'])) {
            return;
        }

        $fields = ['identifier', 'sku', 'code'];
        foreach ($fields as $field) {
            if (empty($item[$field])) {
                continue;
            }

            $parent = mb_strtoupper($item['parent']);
            $variant = mb_strtoupper($item[$field]);
            $this->variants[$parent][$variant] = ['parent' => $parent, 'variant' => $variant];
        }
    }

    public function initialize()
    {
        $this->variants = [];
        $this->cacheProvider->delete('product_variants');
    }

    public function flush()
    {
        if (!$this->variants) {
            return;
        }

        $resolvedVariants = [];

        foreach ($this->variants as $parent => $variants) {
            foreach ($variants as $variantKey => $variant) {
                if (array_key_exists($variantKey, $this->variants)) {
                    foreach ($this->variants[$variantKey] as $resolvedVariantKey => $resolvedVariant) {
                        $resolvedVariant['parent'] = $parent;
                        $resolvedVariants[$parent][$resolvedVariantKey] = $resolvedVariant;
                    }

                    continue;
                }

                $resolvedVariants[$parent][$variantKey] = $variant;
            }
        }

        $this->cacheProvider->save('product_variants', $resolvedVariants);
        $this->variants = [];
    }
}
