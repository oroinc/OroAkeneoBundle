<?php

namespace Oro\Bundle\AkeneoBundle\Provider;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityConfigBundle\Provider\SerializedFieldProvider as BaseSerializedFieldProvider;
use Oro\Bundle\ProductBundle\Provider\VariantField;
use Oro\Bundle\ProductBundle\Provider\VariantFieldProvider as BaseVariantFieldProvider;

class VariantFieldProvider extends BaseVariantFieldProvider
{
    /** @var BaseVariantFieldProvider */
    private $variantFieldProvider;

    /** @var AttributeManager */
    private $attributeManager;

    /** @var BaseSerializedFieldProvider */
    private $serializedFieldProvider;

    public function __construct(
        BaseVariantFieldProvider $variantFieldProvider,
        AttributeManager $attributeManager,
        BaseSerializedFieldProvider $serializedFieldProvider
    ) {
        $this->variantFieldProvider = $variantFieldProvider;
        $this->attributeManager = $attributeManager;
        $this->serializedFieldProvider = $serializedFieldProvider;
    }

    public function getVariantFields(AttributeFamily $attributeFamily)
    {
        $variantFields = $this->variantFieldProvider->getVariantFields($attributeFamily);

        $attributes = $this->attributeManager->getAttributesByFamily($attributeFamily);

        /** @var FieldConfigModel $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->getType() !== 'string') {
                continue;
            }

            if ($this->attributeManager->isSystem($attribute)) {
                continue;
            }

            if (!$this->attributeManager->isActive($attribute)) {
                continue;
            }

            if ($this->serializedFieldProvider->isSerialized($attribute)) {
                continue;
            }

            $fieldName = $attribute->getFieldName();
            $variantFields[$fieldName] = new VariantField(
                $fieldName,
                $this->attributeManager->getAttributeLabel($attribute)
            );
        }

        return $variantFields;
    }
}
