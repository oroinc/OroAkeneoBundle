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
        foreach ($items as &$item) {
            foreach ($item['values'] as $code => &$values) {
                if (empty($this->attributesImageFilter) || in_array($code, $this->attributesImageFilter)) {
                    foreach ($values as $value) {
                        if ('pim_catalog_image' !== $value['type'] || empty($value['data'])) {
                            continue;
                        }

                        $identifier = $item['identifier'] ?? $item['code'];
                        $path = $value['data'];
                        $images[$identifier][$path] = ['SKU' => $identifier, 'Name' => $path];

                        if ($this->getTransport()->isAkeneoMergeImageToParent() && !empty($item['parent'])) {
                            $identifier = $item['parent'];
                            $images[$identifier][$path] = ['SKU' => $identifier, 'Name' => $path];
                        }
                    }
                }
            }
        }

        $this->stepExecution->setReadCount(count($images));

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
                        if ('pim_catalog_image' !== $value['type'] || empty($value['data'])) {
                            continue;
                        }

                        $this->getAkeneoTransport($context)->downloadAndSaveMediaFile('product_images', $value['data']);
                    }
                }
            }
        }
    }
}
