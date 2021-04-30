<?php

namespace Oro\Bundle\AkeneoBundle\Layout\Block\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Layout\AttributeRenderRegistry;
use Oro\Bundle\EntityConfigBundle\Layout\Mapper\AttributeBlockTypeMapperInterface;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Component\Layout\Block\OptionsResolver\OptionsResolver;
use Oro\Component\Layout\Block\Type\AbstractContainerType;
use Oro\Component\Layout\Block\Type\Options;
use Oro\Component\Layout\BlockBuilderInterface;
use Oro\Component\Layout\BlockInterface;
use Oro\Component\Layout\BlockView;

/**
 * Layout block type representing group of Akeneo localizable attributes.
 */
class AttributeGroupType extends AbstractContainerType
{
    /** @var AttributeRenderRegistry */
    private $attributeRenderRegistry;

    /** @var AttributeBlockTypeMapperInterface */
    private $blockTypeMapper;

    /** @var AttributeManager */
    private $attributeManager;

    /** @var AbstractContainerType */
    private $abstractContainerType;

    public function __construct(
        AttributeRenderRegistry $attributeRenderRegistry,
        AttributeManager $attributeManager,
        AttributeBlockTypeMapperInterface $blockTypeMapper,
        AbstractContainerType $abstractContainerType
    ) {
        $this->attributeRenderRegistry = $attributeRenderRegistry;
        $this->attributeManager = $attributeManager;
        $this->blockTypeMapper = $blockTypeMapper;
        $this->abstractContainerType = $abstractContainerType;
    }

    public function buildBlock(BlockBuilderInterface $builder, Options $options)
    {
        $this->abstractContainerType->buildBlock($builder, $options);

        /** @var AttributeFamily $attributeFamily */
        $attributeFamily = $options['attribute_family'];
        $code = $options['group'];
        $entityValue = $options->get('entity', false);
        $attributeGroup = $attributeFamily->getAttributeGroup($code);

        if (null === $attributeGroup) {
            return;
        }

        $excludeFromRest = $options['exclude_from_rest'];

        $options['visible'] = $attributeGroup->getIsVisible();

        if ($excludeFromRest) {
            $this->attributeRenderRegistry->setGroupRendered($attributeFamily, $attributeGroup);
        }

        $layoutManipulator = $builder->getLayoutManipulator();
        $attributeGroupBlockId = $builder->getId();
        $attributes = $this->attributeManager->getAttributesByGroup($attributeGroup);
        foreach ($attributes as $attribute) {
            if ($this->attributeRenderRegistry->isAttributeRendered($attributeFamily, $attribute->getFieldName())) {
                continue;
            }

            if ($attribute->getType() !== RelationType::MANY_TO_MANY) {
                continue;
            }

            if (($attribute->toArray('extend')['target_entity'] ?? null) !== LocalizedFallbackValue::class) {
                continue;
            }

            if (($attribute->toArray('importexport')['source'] ?? null) !== 'akeneo') {
                continue;
            }

            $fieldName = $attribute->getFieldName();
            $blockType = $this->blockTypeMapper->getBlockType($attribute);
            $layoutManipulator->add(
                $this->getAttributeBlockName($fieldName, $blockType, $attributeGroupBlockId),
                $attributeGroupBlockId,
                $blockType,
                array_merge(
                    [
                        'entity' => $entityValue,
                        'fieldName' => $attribute->getFieldName(),
                        'className' => $attribute->getEntity()->getClassName(),
                    ],
                    $options['attribute_options']->toArray()
                )
            );
        }
    }

    public function buildView(BlockView $view, BlockInterface $block, Options $options)
    {
        $this->abstractContainerType->buildView($view, $block, $options);
    }

    private function getAttributeBlockName($field_name, $blockType, $attributeGroupBlockId)
    {
        return sprintf('%s_%s_%s', $attributeGroupBlockId, $blockType, $field_name);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $this->abstractContainerType->configureOptions($resolver);
    }

    public function getName()
    {
        return $this->abstractContainerType->getName();
    }
}
