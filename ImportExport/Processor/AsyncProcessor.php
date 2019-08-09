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

        $fields = ['identifier', 'sku'];
        foreach ($fields as $field) {
            if (!empty($item[$field])) {
                foreach ((array)$item[$field] as $variant) {
                    $this->variants[$item['parent']][$variant] = [
                        'parent' => $item['parent'],
                        'variant' => $variant,
                    ];
                }
            }
        }

        unset($item['parent']);
    }

    public function initialize()
    {
        $this->variants = [];
        $this->cacheProvider->delete('product_variants');
    }

    public function flush()
    {
        $this->cacheProvider->save('product_variants', $this->variants);
        $this->variants = null;
    }
}
