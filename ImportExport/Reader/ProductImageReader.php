<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductImageReader extends IteratorBasedReader
{
    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $items = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('items') ?? [];

        $images = [];
        foreach ($items as &$item) {
            foreach ($item['values'] as &$values) {
                foreach ($values as $value) {
                    if ('pim_catalog_image' !== $value['type'] || empty($value['data'])) {
                        continue;
                    }

                    $images[] = [
                        'SKU' => $item['identifier'] ?? $item['code'],
                        'Name' => basename($value['data']),
                    ];
                }
            }
        }

        $this->stepExecution->setReadCount(count($images));

        $this->setSourceIterator(new \ArrayIterator($images));
    }
}
