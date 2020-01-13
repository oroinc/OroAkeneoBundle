<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\PricingBundle\ImportExport\DataConverter\ProductPriceDataConverter as BaseProductPriceDataConverter;
use Oro\Bundle\PricingBundle\Provider\PriceListProvider;

class ProductPriceDataConverter extends BaseProductPriceDataConverter
{
    use AkeneoIntegrationTrait;

    /**
     * @var PriceListProvider
     */
    protected $priceListProvider;

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $importedRecord['quantity'] = 1;
        $importedRecord['unit'] = ['code' => $this->configManager->get('oro_product.default_unit')];
        $importedRecord['price_list_id'] = $this->getPriceListId();

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }

    /**
     * @return int
     */
    private function getPriceListId()
    {
        $transport = $this->getTransport();

        if (!$transport->getPriceList()) {
            return $this->priceListProvider->getDefaultPriceListId();
        }

        return $transport->getPriceList()->getId();
    }

    public function setPriceListProvider(PriceListProvider $priceListProvider): void
    {
        $this->priceListProvider = $priceListProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaderConversionRules()
    {
        return [
            'sku' => 'product:sku',
            'amount' => 'value',
            'currency' => 'currency',
            'price_list_id' => 'priceList:id',
        ];
    }
}
