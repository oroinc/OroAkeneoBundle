<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\ProductBundle\Entity\ProductImageType;
use Oro\Bundle\ProductBundle\ImportExport\DataConverter\ProductImageDataConverter as BaseProductImageDataConverter;

class ProductImageDataConverter extends BaseProductImageDataConverter
{
    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $importedRecord['types'][ProductImageType::TYPE_MAIN] = false;
        $importedRecord['types'][ProductImageType::TYPE_LISTING] = false;
        $importedRecord['types'][ProductImageType::TYPE_ADDITIONAL] = true;

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }
}
