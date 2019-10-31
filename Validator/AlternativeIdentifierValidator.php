<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Oro\Bundle\AkeneoBundle\Validator\Constraints\AlternativeIdentifierConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AlternativeIdentifierValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        /* @var AlternativeIdentifierConstraint $constraint */
        if (!$this->isValid($value) && !empty($value)) {
            $this->context->addViolation($constraint->message);
        }
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isValid($value)
    {
        preg_match_all('/([^a-zA-Z0-9_:])/m', $value, $matches, PREG_SET_ORDER, 0);

        return count($matches) == 0;
    }
}
