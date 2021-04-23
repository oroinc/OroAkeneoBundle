<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\ORM\ORMInvalidArgumentException;

/**
 * @method assertEnvironment
 * @method beforeProcessEntity
 * @method processEntity
 * @method afterProcessEntity
 * @method validateAndUpdateContext
 * @property $cachedEntities
 * @property $processingEntity
 * @property $relatedEntityStateHelper
 * @property $context
 * @property $doctrineHelper
 *
 * @internal Append proper validation logic to stategies
 * @deprecated BAP-20243
 */
trait StrategyValidationTrait
{
    public function process($entity)
    {
        $this->assertEnvironment($entity);

        $this->cachedEntities = [];
        $this->processingEntity = null;
        $this->relatedEntityStateHelper->clear();

        $source = $entity;
        if (!$entity = $this->validateBeforeProcess($entity)) {
            $this->invalidateEntity($source);

            return null;
        }

        $source = $entity;
        if (!$entity = $this->beforeProcessEntity($entity)) {
            $this->invalidateEntity($source);

            return null;
        }

        $source = $entity;
        if (!$entity = $this->processEntity($entity, true, true, $this->context->getValue('itemData'))) {
            $this->invalidateEntity($source);

            return null;
        }

        $source = $entity;
        if (!$entity = $this->afterProcessEntity($entity)) {
            $this->invalidateEntity($source);

            return null;
        }

        return $this->validateAndUpdateContext($entity);
    }

    protected function processValidationErrors($entity, array $validationErrors)
    {
        parent::processValidationErrors($entity, $validationErrors);

        $this->invalidateEntity($entity);
    }

    protected function invalidateEntity($entity)
    {
        $this->relatedEntityStateHelper->revertRelations();

        if (!$entity) {
            return;
        }

        $em = $this->doctrineHelper->getEntityManager($entity, false);
        if (!$em) {
            return;
        }

        try {
            $em->refresh($entity);
        } catch (ORMInvalidArgumentException $e) {
            $em->detach($entity);
        }
    }

    protected function validateBeforeProcess($entity)
    {
        $validationErrors = $this->strategyHelper->validateEntity($entity, null, ['import_field_type_akeneo']);
        if ($validationErrors) {
            $this->context->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $this->context);

            return null;
        }

        return $entity;
    }
}
