<?php

namespace Oro\Bundle\AkeneoBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AlternativeIdentifierConstraint extends Constraint
{
    /**
     * @var string
     */
    public $message = 'oro.akeneo.validator.alternative_identifier.message';

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
        return 'oro_akeneo.alternative_identifier_validator';
    }
}
