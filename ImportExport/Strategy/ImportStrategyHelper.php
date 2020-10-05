<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Exception\InvalidArgumentException;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper as BaseImportStrategyHelper;

/**
 * Overrides base importEntity method to increase performance.
 */
class ImportStrategyHelper extends BaseImportStrategyHelper
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * {@inheritdoc}
     *
     * Collection's entities ids already set, so compare collections by ids
     * @see \Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy::updateRelations
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function importEntity($databaseEntity, $importedEntity, array $excludedProperties = [])
    {
        $databaseEntityClass = ClassUtils::getClass($databaseEntity);
        if ($databaseEntityClass != ClassUtils::getClass($importedEntity)) {
            throw new InvalidArgumentException('database and imported entities must be instances of the same class');
        }

        $entityProperties = $this->getEntityPropertiesByClassName($databaseEntityClass);
        $importedEntityProperties = array_diff($entityProperties, $excludedProperties);

        foreach ($importedEntityProperties as $propertyName) {
            // we should not overwrite deleted fields
            if ($this->isDeletedField($databaseEntityClass, $propertyName)) {
                continue;
            }

            $importedValue = $this->fieldHelper->getObjectValue($importedEntity, $propertyName);
            $databaseValue = $this->fieldHelper->getObjectValue($databaseEntity, $propertyName);

            if ($importedValue instanceof Collection && $databaseValue instanceof Collection) {
                $databaseValueEntitiesIds = [];
                foreach ($databaseValue as $databaseValueEntityKey => $databaseValueEntity) {
                    $databaseValueEntityIds = (string)$this->doctrineHelper
                        ->getSingleEntityIdentifier($databaseValueEntity);
                    if (!$databaseValueEntityIds) {
                        $databaseValue->removeElement($databaseValueEntity);

                        continue;
                    }

                    $databaseValueEntitiesIds[$databaseValueEntityIds] = $databaseValueEntity;
                }

                foreach ($importedValue as $importedValueEntityKey => $importedValueEntity) {
                    $importedValueEntityIds = (string)$this->doctrineHelper
                        ->getSingleEntityIdentifier($importedValueEntity);
                    if (!$importedValueEntityIds) {
                        $databaseValue->add($importedValueEntity);

                        continue;
                    }

                    unset($databaseValueEntitiesIds[$importedValueEntityIds]);
                    if ($databaseValue->contains($importedValueEntity)) {
                        continue;
                    }

                    $databaseValue->add($importedValueEntity);
                }

                foreach ($databaseValueEntitiesIds as $databaseValueEntityIds => $databaseValueEntity) {
                    unset($databaseValueEntitiesIds[$databaseValueEntityIds]);

                    $databaseValue->removeElement($databaseValueEntity);
                }

                continue;
            }

            $this->fieldHelper->setObjectValue($databaseEntity, $propertyName, $importedValue);
        }
    }

    /**
     * {@inheritdoc}
     */
    private function getEntityPropertiesByClassName($entityClassName)
    {
        /*
         * In case if we work with configured entities then we should use fieldHelper
         * to getting fields because it won't returns any hidden fields (f.e snapshot fields)
         * that mustn't be changed by import/export
         */
        if ($this->extendConfigProvider->hasConfig($entityClassName)) {
            $properties = $this->fieldHelper->getFields(
                $entityClassName,
                true
            );

            return array_column($properties, 'name');
        }

        $entityMetadata = $this
            ->getEntityManager($entityClassName)
            ->getClassMetadata($entityClassName);

        return array_merge(
            $entityMetadata->getFieldNames(),
            $entityMetadata->getAssociationNames()
        );
    }

    public function addValidationErrors(array $validationErrors, ContextInterface $context, $errorPrefix = null)
    {
        if (AkeneoChannel::TYPE !== $context->getOption('channelType')) {
            return parent::addValidationErrors($validationErrors, $context, $errorPrefix);
        }

        foreach ($validationErrors as $validationError) {
            $context->addError(
                $this->translator->trans(
                    'oro.akeneo.error',
                    [
                        '%error%' => $validationError,
                        '%item%' => json_encode(
                            $context->getValue('rawItemData'),
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                    ]
                )
            );
        }
    }

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper): void
    {
        $this->doctrineHelper = $doctrineHelper;
    }
}
