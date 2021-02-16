<?php

namespace Oro\Bundle\AkeneoBundle\Validator;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Validator\Constraints\ConfigurableProductAccessorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UniqueVariantLinksSimpleProductValidator extends ConstraintValidator
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
        if (!is_a($value, Product::class)) {
            throw new \InvalidArgumentException(sprintf('Entity must be instance of "%s", "%s" given', Product::class, is_object($value) ? get_class($value) : gettype($value)));
        }

        if ($value->isConfigurable() || $value->getParentVariantLinks()->count() === 0) {
            return;
        }

        $uow = $this->doctrineHelper->getEntityManagerForClass(Product::class)->getUnitOfWork();
        $collections = array_merge($uow->getScheduledCollectionUpdates(), $uow->getScheduledCollectionDeletions());
        if (
            !in_array($value->getVariantLinks(), $collections)
            && !in_array($value->getParentVariantLinks(), $collections)
            && empty($uow->getEntityChangeSet($value)['variantFields'])
        ) {
            return;
        }

        $this->validator->validate($value, $constraint);
    }
}
