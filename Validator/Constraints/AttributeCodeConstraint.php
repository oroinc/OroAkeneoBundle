<?php

namespace Oro\Bundle\AkeneoBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AttributeCodeConstraint extends Constraint
{
    /**
     * @var string
     */
    public $message = 'oro.akeneo.validator.attribute_code.message';

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'oro_akeneo.attribute_code_validator';
    }
}
