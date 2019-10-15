<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Oro\Bundle\AkeneoBundle\Validator\Constraints\AttributeCodeConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AttributeCodeValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        /* @var AttributeCodeConstraint $constraint */
        if (!$this->isAkeneoConform($value) && !empty($value)) {
            $this->context->addViolation($constraint->message);
        }
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isAkeneoConform($value)
    {
        preg_match_all('/([^a-zA-Z0-9_;])/m', $value, $matches, PREG_SET_ORDER, 0);

        return count($matches) == 0;
    }
}
