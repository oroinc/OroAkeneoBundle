<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\AbstractLocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\ProductBundle\Entity\Brand;

/**
 * @property $fieldHelper
 * @property $databaseHelper
 * @method isLocalizedFallbackValue
 *
 * @internal Performance fix for LocalizedFallbackValueAwareStrategy
 * @deprecated BAP-20243
 */
trait LocalizedFallbackValueAwareStrategyTrait
{
    protected function generateSearchContextForRelationsUpdate($entity, $entityName, $fieldName, $isPersistRelation)
    {
        $searchContext = parent::generateSearchContextForRelationsUpdate(
            $entity,
            $entityName,
            $fieldName,
            $isPersistRelation
        );

        $fields = $this->fieldHelper->getRelations($entityName);

        if (!$this->isLocalizedFallbackValue($fields[$fieldName])) {
            return $searchContext;
        }

        /** @var Collection $importedCollection */
        $importedCollection = $this->fieldHelper->getObjectValue($entity, $fieldName);
        if ($importedCollection->isEmpty()) {
            return $searchContext;
        }

        $existingEntity = $this->findExistingEntity($entity);
        if ($existingEntity) {
            $searchContext = [];
            $sourceCollection = $this->fieldHelper->getObjectValue($existingEntity, $fieldName);
            /** @var AbstractLocalizedFallbackValue $sourceValue */
            foreach ($sourceCollection as $sourceValue) {
                $localizationCode = LocalizationCodeFormatter::formatName($sourceValue->getLocalization());
                $searchContext[$localizationCode] = $sourceValue;
            }

            return $searchContext;
        }

        return $searchContext;
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        return $this->findExistingEntityTrait($entity, $searchContext);
    }

    public function findExistingEntityTrait($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if ($entity instanceof Brand && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Brand::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if (is_a($entity, AbstractLocalizedFallbackValue::class, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        return $this->findExistingEntityByIdentityFieldsTrait($entity, $searchContext);
    }

    public function findExistingEntityByIdentityFieldsTrait($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Category::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if ($entity instanceof Brand && $entity->getAkeneoCode()) {
            return $this->databaseHelper->findOneBy(
                Brand::class,
                ['akeneo_code' => $entity->getAkeneoCode(), 'channel' => $this->getChannel()]
            );
        }

        if (is_a($entity, AbstractLocalizedFallbackValue::class, true)) {
            $localizationCode = LocalizationCodeFormatter::formatName($entity->getLocalization());

            return $searchContext[$localizationCode] ?? null;
        }

        return parent::findExistingEntityByIdentityFields($entity, $searchContext);
    }

    protected function mapCollections(Collection $importedCollection, Collection $sourceCollection)
    {
    }

    protected function setLocalizationKeys($entity, array $field)
    {
    }

    protected function removeNotInitializedEntities($entity, array $field, array $relations)
    {
    }

    private function getChannel()
    {
        return $this->doctrineHelper->getEntityReference(Channel::class, $this->context->getOption('channel'));
    }
}
