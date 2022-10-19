<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Oro\Bundle\AkeneoBundle\Validator\Constraints\JsonConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class JsonValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        /* @var JsonConstraint $constraint */
        if (!$this->isJSON($value) && strlen($value) > 0) {
            $this->context->addViolation($constraint->message);
        }
    }

    /**
     * @return bool
     */
    private function isJSON($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) && \JSON_ERROR_NONE == json_last_error();
    }
}
