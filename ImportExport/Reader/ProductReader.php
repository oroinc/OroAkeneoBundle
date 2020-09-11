<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductReader extends IteratorBasedReader
{
    use AkeneoTransportTrait;

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $items = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('items') ?? [];

        if (!empty($items)) {
            $this->processFileTypeDownload($items, $context);
        }

        $this->stepExecution->setReadCount(count($items));

        $this->setSourceIterator(new \ArrayIterator($items));
    }

    protected function processFileTypeDownload(array $items, ContextInterface $context)
    {
        foreach ($items as $item) {
            foreach ($item['values'] as $values) {
                foreach ($values as $value) {
                    if (empty($value['data'])) {
                        continue;
                    }

                    if (in_array($value['type'], ['pim_catalog_image', 'pim_catalog_file'])) {
                        $this->getAkeneoTransport($context)->downloadAndSaveMediaFile($value['data']);
                    }

                    if (in_array($value['type'], ['pim_assets_collection'])) {
                        if (!is_array($value['data'])) {
                            continue;
                        }

                        foreach ($value['data'] as $code => $file) {
                            $this->getAkeneoTransport($context)->downloadAndSaveAsset($code, $file);
                        }
                    }
                }
            }
        }
    }
}
