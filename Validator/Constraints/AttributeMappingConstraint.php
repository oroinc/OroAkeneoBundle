<?php

namespace Oro\Bundle\AkeneoBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AttributeMappingConstraint extends Constraint
{
    public $message = 'oro.akeneo.validator.attribute_mapping.message';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy()
    {
        return 'oro_akeneo.attribute_mapping_validator';
    }
}
