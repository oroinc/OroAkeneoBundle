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
    private $attributes = [];

    /**
     * @var array
     */
    private $familyVariants = [];

    /**
     * @var string|null
     */
    private $alternativeAttribute;

    /**
     * AttributeIterator constructor.
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimEnterpriseClientInterface $client,
        LoggerInterface $logger,
        array $attributes,
        array $familyVariants,
        ?string $alternativeAttribute = null
    ) {
        parent::__construct($resourceCursor, $client, $logger);
        $this->attributes = $attributes;
        $this->alternativeAttribute = $alternativeAttribute;
        $this->familyVariants = $familyVariants;
    }

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $product = $this->resourceCursor->current();

        $this->setAlternativeIdentifier($product);
        $this->setValueAttributeTypes($product);
        $this->setFamilyVariant($product);

        return $product;
    }

    /**
     * Switch the product code (intern identifier in Akeneo) value
     * with an other attribute to allow to map it differently
     */
    protected function setAlternativeIdentifier(array &$product)
    {
        if (null === $this->alternativeAttribute) return;

        @list($altAttribute, $identifier) = explode(':', $this->alternativeAttribute);

        if (!empty($altAttribute)
            && isset($product['values'][$altAttribute])
            && isset($product['identifier'])
        ) {

            if (isset($product['values'][$altAttribute][0]['data'])) {
                if (null !== $identifier) {
                    $product[$identifier] = $product['identifier'];
                }

                $product['identifier'] = $product['values'][$altAttribute][0]['data'];
            }
        }
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
