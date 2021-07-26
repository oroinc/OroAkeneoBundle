<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\AkeneoPimClientInterface;
use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Psr\Log\LoggerInterface;

class ProductIterator extends AbstractIterator
{
    private $attributes = [];

    private $familyVariants = [];

    private $measureFamilies = [];

    private $attributeMapping = [];

    private $assets = [];

    private $assetsFamily = [];

    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimClientInterface $client,
        LoggerInterface $logger,
        array $attributes = [],
        array $familyVariants = [],
        array $measureFamilies = [],
        array $attributeMapping = []
    ) {
        parent::__construct($resourceCursor, $client, $logger);

        $this->attributes = $attributes;
        $this->familyVariants = $familyVariants;
        $this->measureFamilies = $measureFamilies;
        $this->attributeMapping = $attributeMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $product = $this->resourceCursor->current();

        $this->setSku($product);
        $this->setValueAttributeTypes($product);
        $this->setFamilyVariant($product);
        $this->setAssetCode($product);

        return $product;
    }

    /**
     * Set attribute types for product values.
     */
    protected function setValueAttributeTypes(array &$product)
    {
        foreach ($product['values'] as $code => $values) {
            if (isset($this->attributes[$code])) {
                foreach ($values as $key => $value) {
                    $product['values'][$code][$key]['type'] = $this->attributes[$code]['type'];

                    if (!isset($value['data']['unit'])) {
                        continue;
                    }

                    if (array_key_exists($value['data']['unit'], $this->measureFamilies)) {
                        $symbol = $this->measureFamilies[$value['data']['unit']];

                        $product['values'][$code][$key]['data']['symbol'] = $symbol;
                    }
                }
            } else {
                unset($product['values'][$code]);
            }
        }
    }

    /**
     * Set family variant from API.
     */
    private function setFamilyVariant(array &$model)
    {
        if (empty($model['family_variant'])) {
            return;
        }

        if (isset($this->familyVariants[$model['family_variant']])) {
            $model['family_variant'] = $this->familyVariants[$model['family_variant']];
        }
    }

    private function setAssetCode(array &$product): void
    {
        foreach ($product['values'] as $code => &$values) {
            foreach ($values as $key => &$value) {
                if ($value['type'] === 'pim_assets_collection') {
                    $data = [];
                    $source = (array)$value['data'];
                    $value['data'] = [];
                    foreach ($source as $assetCode) {
                        if (array_key_exists($assetCode, $this->assets)) {
                            $data = $this->assets[$assetCode];

                            continue;
                        }

                        $assetData = $this->client->getAssetApi()->get($assetCode);
                        $assets = $assetData['reference_files'] ?? [];
                        foreach ($assets as $asset) {
                            if (empty($asset['code'])) {
                                continue;
                            }

                            $this->assets[$assetCode][] = $asset['code'];
                            $data[$assetCode] = $this->assets[$assetCode];
                        }
                    }
                    $value['data'] = $data;
                }

                if ($value['type'] === 'pim_catalog_asset_collection') {
                    $data = [];
                    $source = (array)$value['data'];
                    $value['data'] = [];
                    $assetFamily = $this->attributes[$code]['reference_data_name'] ?? null;
                    if (!$assetFamily) {
                        continue;
                    }

                    $assetFamilyData = $this->getAssetsFamily($assetFamily);
                    $valueField = $assetFamilyData['attribute_as_main_media'] ?? null;
                    if (!$valueField) {
                        continue;
                    }

                    foreach ($source as $assetCode) {
                        if (array_key_exists($assetFamily . $assetCode, $this->assets)) {
                            $data = array_merge($data, $this->assets[$assetFamily . $assetCode]);

                            continue;
                        }

                        $this->assets[$assetFamily . $assetCode] = [];

                        $assetData = $this->client->getAssetManagerApi()->get($assetFamily, $assetCode);
                        $assets = $assetData['values'][$valueField] ?? [];
                        foreach ($assets as $asset) {
                            if (empty($asset['data'])) {
                                continue;
                            }

                            if (!pathinfo($asset['data'], \PATHINFO_EXTENSION)) {
                                continue;
                            }

                            $this->assets[$assetFamily . $assetCode][$assetCode] = $asset['data'];
                            $data[$assetCode] = $asset['data'];
                        }
                    }
                    $value['data'] = $data;
                }
            }
        }
    }

    private function setSku(array &$product): void
    {
        $sku = $product['identifier'] ?? $product['code'];

        if (array_key_exists('sku', $this->attributeMapping)) {
            if (!empty($product['values'][$this->attributeMapping['sku']][0]['data'])) {
                $sku = $product['values'][$this->attributeMapping['sku']][0]['data'];
            }
        }

        $product['sku'] = (string)$sku;
    }

    private function getAssetsFamily(string $assetFamily): array
    {
        if (array_key_exists($assetFamily, $this->assetsFamily)) {
            return $this->assetsFamily[$assetFamily] ?? [];
        }

        $this->assetsFamily[$assetFamily] = $this->client->getAssetFamilyApi()->get($assetFamily);

        return $this->assetsFamily[$assetFamily] ?? [];
    }
}
