<?php

namespace Oro\Bundle\AkeneoBundle\ProductVariant\TypeHandler;

use Oro\Bundle\ProductBundle\ProductVariant\Registry\ProductVariantTypeHandlerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactory;

class StringTypeHandler implements ProductVariantTypeHandlerInterface
{
    const TYPE = 'string';

    /** @var FormFactory */
    protected $formFactory;

    public function __construct(FormFactory $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    public function createForm($fieldName, array $availability, array $options = [])
    {
        $options = array_merge($this->getOptions($fieldName, $availability), $options);

        return $this->formFactory->createNamed($fieldName, ChoiceType::class, null, $options);
    }

    public function getType()
    {
        return self::TYPE;
    }

    private function getOptions(string $fieldName, array $availability): array
    {
        $values = array_keys($availability);

        return [
            'auto_initialize' => false,
            'choices' => array_combine($values, $values),
        ];
    }
}
