<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoPimExtendableClientInterface;
use Psr\Log\LoggerInterface;

class ProductIterator extends AbstractIterator
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $familyVariants = [];

    /**
     * @var array
     */
    private $measureFamilies = [];

    /**
     * AttributeIterator constructor.
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimExtendableClientInterface $client,
        LoggerInterface $logger,
        array $attributes,
        array $familyVariants,
        array $measureFamilies
    ) {
        parent::__construct($resourceCursor, $client, $logger);
        $this->attributes = $attributes;
        $this->familyVariants = $familyVariants;
        $this->measureFamilies = $measureFamilies;
    }

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $product = $this->resourceCursor->current();

        $this->setValueAttributeTypes($product);
        $this->setFamilyVariant($product);

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
}
