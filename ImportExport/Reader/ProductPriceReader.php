<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductPriceReader extends IteratorBasedReader
{
    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $items = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('items') ?? [];

        $prices = [];

        foreach ($items as &$item) {
            if (!isset($item['values']) || !is_array($item['values'])) {
                continue;
            }

            foreach ($item['values'] as $values) {
                foreach ($values as $value) {
                    if ('pim_catalog_price_collection' !== $value['type']) {
                        continue;
                    }

                    foreach ($value['data'] as &$price) {
                        $price['sku'] = $item['sku'];
                        if ($price != array_filter($price)) {
                            continue;
                        }

                        $prices[$price['sku'] . '_' . $price['currency']] = $price;
                    }
                }
            }
        }

        $this->stepExecution->setReadCount(0);

        $this->setSourceIterator(new \ArrayIterator($prices));
    }
}
