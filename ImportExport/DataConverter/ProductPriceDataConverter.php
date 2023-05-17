<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\AkeneoBundle\ImportExport\AkeneoIntegrationTrait;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\ImportExport\DataConverter\ProductPriceDataConverter as BaseProductPriceDataConverter;

class ProductPriceDataConverter extends BaseProductPriceDataConverter
{
    use AkeneoIntegrationTrait;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

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
            return $this->getDefaultPriceListId();
        }

        return $transport->getPriceList()->getId();
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

    public function getDefaultPriceList(): ?PriceList
    {
        $defaultPriceListId = $this->getDefaultPriceListId();
        if (!$defaultPriceListId) {
            return null;
        }

        return $this->doctrineHelper
            ->getEntityManagerForClass(PriceList::class)
            ->getRepository(PriceList::class)
            ->find($defaultPriceListId);
    }

    public function getDefaultPriceListId(): ?int
    {
        return $this->configManager->get('oro_pricing.default_price_list');
    }
}
