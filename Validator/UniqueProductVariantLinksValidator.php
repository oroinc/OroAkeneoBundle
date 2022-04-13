<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ProductBundle\Validator\Constraints\ConfigurableProductAccessorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UniqueProductVariantLinksValidator extends ConstraintValidator
{
    use ConfigurableProductAccessorTrait;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var ConstraintValidatorInterface */
    private $validator;

    public function __construct(DoctrineHelper $doctrineHelper, ConstraintValidatorInterface $validator)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->validator = $validator;
    }

    public function initialize(ExecutionContextInterface $context)
    {
        $this->validator->initialize($context);

        parent::initialize($context);
    }

    public function validate($value, Constraint $constraint)
    {
        $product = $this->getConfigurableProduct($value, $constraint);
        if ($product === null) {
            return;
        }
        if (count($product->getVariantFields()) === 0) {
            return;
        }

        $variantLinks = $value->getVariantLinks();
        if ($variantLinks instanceof PersistentCollection) {
            if ($variantLinks->isInitialized() && !$variantLinks->isDirty()) {
                return;
            }
        }

        $this->validator->validate($value, $constraint);
    }
}
