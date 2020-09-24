<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\AkeneoBundle\ImportExport\AkeneoIntegrationTrait;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

class ProductImageReader extends IteratorBasedReader
{
    use AkeneoTransportTrait;
    use AkeneoIntegrationTrait;

    /**
     * @var array
     */
    private $attributesImageFilter = [];

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    protected function initializeFromContext(ContextInterface $context)
    {
        $this->setImportExportContext($context);
        parent::initializeFromContext($context);

        $this->initAttributesImageList();

        $items = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('items') ?? [];

        if (!empty($items)) {
            $this->processImagesDownload($items, $context);
        }

        $images = [];
        foreach ($items as $item) {
            foreach ($item['values'] as $code => $values) {
                if (empty($this->attributesImageFilter) || in_array($code, $this->attributesImageFilter)) {
                    foreach ($values as $value) {
                        if (empty($value['data'])) {
                            continue;
                        }

                        if (!in_array($value['type'], ['pim_catalog_image', 'pim_assets_collection'])) {
                            continue;
                        }

                        foreach ((array)$value['data'] as $path) {
                            $sku = $item['sku'];
                            $images[$sku][$path] = ['SKU' => $sku, 'Name' => $path, 'uri' => $path];

                            if ($this->getTransport()->isAkeneoMergeImageToParent() && !empty($item['parent'])) {
                                $sku = $item['parent'];
                                $images[$sku][$path] = ['SKU' => $sku, 'Name' => $path, 'uri' => $path];
                            }
                        }
                    }
                }
            }
        }

        $this->stepExecution->setReadCount(0);

        $this->setSourceIterator(new \ArrayIterator($images));
    }

    protected function initAttributesImageList()
    {
        $this->attributesImageFilter = [];
        $list = $this->getTransport()->getAkeneoAttributesImageList();
        if (!empty($list)) {
            $this->attributesImageFilter = explode(';', $list);
        }
    }

    protected function processImagesDownload(array $items, ContextInterface $context)
    {
        foreach ($items as $item) {
            foreach ($item['values'] as $code => $values) {
                if (empty($this->attributesImageFilter) || in_array($code, $this->attributesImageFilter)) {
                    foreach ($values as $value) {
                        if (empty($value['data'])) {
                            continue;
                        }

                        if (in_array($value['type'], ['pim_catalog_image'])) {
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
}
