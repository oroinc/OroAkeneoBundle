<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\EntityConfigBundle\Generator\SlugGenerator;
use Oro\Bundle\LocaleBundle\Entity\AbstractLocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;
use Oro\Bundle\ProductBundle\Entity\Brand;

class BrandImportStrategy extends LocalizedFallbackValueAwareStrategy implements ClosableInterface
{
    use LocalizedFallbackValueAwareStrategyTrait;
    use StrategyRelationsTrait;
    use StrategyValidationTrait;

    /** @var SlugGenerator */
    private $slugGenerator;

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];

        $this->databaseHelper->onClear();
    }

    protected function afterProcessEntity($entity)
    {
        if ($entity->getSlugPrototypes()->isEmpty()) {
            foreach ($entity->getNames() as $localizedName) {
                $this->addSlugPrototype($entity, $localizedName);
            }
        }

        if (!$entity->getDefaultSlugPrototype() && $entity->getDefaultName()) {
            $this->addSlugPrototype($entity, $entity->getDefaultName());
        }

        return parent::afterProcessEntity($entity);
    }

    private function addSlugPrototype(Brand $brand, AbstractLocalizedFallbackValue $localizedName): void
    {
        $localizedSlug = new LocalizedFallbackValue();
        $localizedSlug->setString($this->slugGenerator->slugify($localizedName->getString()));
        $localizedSlug->setFallback($localizedName->getFallback());
        $localizedSlug->setLocalization($localizedName->getLocalization());
        $brand->addSlugPrototype($localizedSlug);
    }

    protected function updateContextCounters($entity)
    {
        $identifier = $this->databaseHelper->getIdentifier($entity);
        if ($identifier || $this->newEntitiesHelper->getEntityUsage($this->getEntityHashKey($entity)) > 1) {
            $this->context->incrementUpdateCount();
        } else {
            $this->context->incrementAddCount();
        }
    }

    protected function isFieldExcluded($entityName, $fieldName, $itemData = null)
    {
        $excludeBrandFields = [
            'slugs',
            'slugPrototypes',
            'slugPrototypesWithRedirect',
        ];

        if (is_a($entityName, Brand::class, true) && in_array($fieldName, $excludeBrandFields)) {
            return true;
        }

        return parent::isFieldExcluded($entityName, $fieldName, $itemData);
    }

    public function setSlugGenerator(SlugGenerator $slugGenerator): void
    {
        $this->slugGenerator = $slugGenerator;
    }
}
