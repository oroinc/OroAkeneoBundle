<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Psr\Log\LoggerInterface;

class ProductIterator extends AbstractIterator
{
    /**
     * @var array
     */
    private $attributesTypeMapping;

    /**
     * @var array
     */
    private $familyVariants = [];

    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimEnterpriseClientInterface $client,
        LoggerInterface $logger,
        array $attributesTypeMapping,
        array $familyVariants
    ) {
        parent::__construct($resourceCursor, $client, $logger);
        $this->attributesTypeMapping = $attributesTypeMapping;
        $this->familyVariants = $familyVariants;
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
            if (isset($this->attributesTypeMapping[$code])) {
                foreach ($values as $key => $value) {
                    $product['values'][$code][$key]['type'] = $this->attributesTypeMapping[$code];
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
