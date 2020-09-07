<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Oro\Bundle\AkeneoBundle\Validator\Constraints\AttributeMappingConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AttributeMappingValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var AttributeMappingConstraint $constraint */
        if (!empty($value) && !$this->isValid($value)) {
            $this->context->addViolation($constraint->message);
        }
    }

    private function isValid($value)
    {
        preg_match_all('/([a-z0-9_]+[:;]?)+/i', $value, $matches, PREG_SET_ORDER, 0);

        return count($matches) == 1;
    }
}
