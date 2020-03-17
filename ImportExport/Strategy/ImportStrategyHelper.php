<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\ImportExportBundle\Exception\InvalidArgumentException;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper as BaseImportStrategyHelper;

/**
 * Overrides base importEntity method to increase performance.
 */
class ImportStrategyHelper extends BaseImportStrategyHelper
{
    /**
     * {@inheritdoc}
     *
     * Collection's entities ids already set, so compare collections by ids
     * @see \Oro\Bundle\ImportExportBundle\Strategy\Import\ConfigurableAddOrReplaceStrategy::updateRelations
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function importEntity($basicEntity, $importedEntity, array $excludedProperties = [])
    {
        $basicEntityClass = ClassUtils::getClass($basicEntity);
        if ($basicEntityClass != ClassUtils::getClass($importedEntity)) {
            throw new InvalidArgumentException('Basic and imported entities must be instances of the same class');
        }

        $entityProperties = $this->getEntityPropertiesByClassName($basicEntityClass);
        $importedEntityProperties = array_diff($entityProperties, $excludedProperties);

        foreach ($importedEntityProperties as $propertyName) {
            // we should not overwrite deleted fields
            if ($this->isDeletedField($basicEntityClass, $propertyName)) {
                continue;
            }

            $importedValue = $this->fieldHelper->getObjectValue($importedEntity, $propertyName);
            $basicValue = $this->fieldHelper->getObjectValue($basicEntity, $propertyName);

            if ($importedValue instanceof Collection && $basicValue instanceof Collection) {
                if ($basicValue instanceof PersistentCollection && !$basicValue->isInitialized() && !$basicValue->isDirty()) {
                    continue;
                }

                if ($importedValue->isEmpty()) {
                    $basicValue->clear();

                    continue;
                }

                if ($basicValue->isEmpty()) {
                    foreach ($importedValue as $importedValueEntity) {
                        $basicValue->add($importedValueEntity);
                    }

                    continue;
                }

                $toAdd = [];
                $toRemove = [];
                $toReplace = [];
                $map = [];

                $basicValueEntitiesIds = [];
                foreach ($basicValue as $basicValueEntityKey => $basicValueEntity) {
                    $basicValueEntityIds = $this->getIdentityValues($basicValueEntity);
                    if (!$basicValueEntityIds) {
                        $toRemove[$basicValueEntityKey] = $basicValueEntityKey;

                        continue;
                    }

                    $basicValueEntityId = md5(json_encode($basicValueEntityIds));
                    $basicValueEntitiesIds[$basicValueEntityId] = $basicValueEntity;
                    $map[$basicValueEntityId] = $basicValueEntityKey;
                }

                $importedValueEntitiesIds = [];
                foreach ($importedValue as $importedValueEntityKey => $importedValueEntity) {
                    $importedValueEntityIds = $this->getIdentityValues($importedValueEntity);
                    if (!$importedValueEntityIds) {
                        $toAdd[] = $importedValueEntity;

                        continue;
                    }

                    $importedValueEntityId = md5(json_encode($importedValueEntityIds));
                    $importedValueEntitiesIds[$importedValueEntityId] = $importedValueEntity;
                    if (array_key_exists($importedValueEntityId, $map)) {
                        $toReplace[$map[$importedValueEntityId]] = $importedValueEntity;

                        continue;
                    }

                    $toAdd[] = $importedValueEntity;
                }

                foreach ($basicValueEntitiesIds as $basicValueEntitiesId => $basicValueEntity) {
                    if (!array_key_exists($basicValueEntitiesId, $importedValueEntitiesIds)) {
                        $toRemove[] = $basicValueEntity;
                    }
                }

                foreach ($toRemove as $entity) {
                    $basicValue->removeElement($entity);
                }

                foreach ($toAdd as $entity) {
                    $basicValue->add($entity);
                }

                foreach ($toReplace as $key => $entity) {
                    $basicValue->set($key, $entity);
                }

                continue;
            }

            $this->fieldHelper->setObjectValue($basicEntity, $propertyName, $importedValue);
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

    /**
     * Gets identity values for entity.
     *
     * @param $entity
     */
    private function getIdentityValues($entity): array
    {
        $identityValues = [];
        $identityFields = $this->fieldHelper->getIdentityValues($entity);

        foreach ($identityFields as $identityFieldName => $identityField) {
            if ($identityField instanceof Collection) {
                foreach ($identityField as $identityFieldCollectionItem) {
                    $identityValues[$identityFieldName][] = $this->fieldHelper
                        ->getIdentityValues($identityFieldCollectionItem);
                }
            } elseif (is_object($identityField)) {
                $identityValues[$identityFieldName] = $this->fieldHelper->getIdentityValues($identityField);
            } else {
                $identityValues[$identityFieldName] = $identityField;
            }
        }

        return $identityValues;
    }
}
