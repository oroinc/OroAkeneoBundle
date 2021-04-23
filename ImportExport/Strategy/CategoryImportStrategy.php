<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityConfigBundle\Generator\SlugGenerator;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\AbstractLocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;

/**
 * Strategy to import categories.
 */
class CategoryImportStrategy extends LocalizedFallbackValueAwareStrategy implements ClosableInterface
{
    use LocalizedFallbackValueAwareStrategyTrait;
    use StrategyRelationsTrait;
    use StrategyValidationTrait;

    /** @var SlugGenerator */
    private $slugGenerator;

    /**
     * @var Category[]
     *
     * Cache existing category request in scope of a single row processing to avoid excess DB queries
     */
    private $existingCategories = [];

    /** @var int */
    private $rootCategoryId;

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];

        $this->existingCategories = [];
        $this->rootCategoryId = null;

        $this->databaseHelper->onClear();
    }

    protected function beforeProcessEntity($entity)
    {
        if ($entity instanceof Category) {
            $parent = $entity->getParentCategory();
            if ($parent instanceof Category && !$parent->getId()) {
                $existingParent = $this->findExistingEntity($parent) ?? $this->getRootCategory();
                $entity->setParentCategory($existingParent);
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    /** @param Category $entity */
    protected function afterProcessEntity($entity)
    {
        if ($entity->getSlugPrototypes()->isEmpty()) {
            foreach ($entity->getTitles() as $localizedTitle) {
                $this->addSlugPrototype($entity, $localizedTitle);
            }
        }

        if (!$entity->getDefaultSlugPrototype() && $entity->getDefaultTitle()) {
            $this->addSlugPrototype($entity, $entity->getDefaultTitle());
        }

        return parent::afterProcessEntity($entity);
    }

    private function addSlugPrototype(Category $category, AbstractLocalizedFallbackValue $localizedName): void
    {
        $localizedSlug = new LocalizedFallbackValue();
        $localizedSlug->setString($this->slugGenerator->slugify($localizedName->getString()));
        $localizedSlug->setFallback($localizedName->getFallback());
        $localizedSlug->setLocalization($localizedName->getLocalization());
        $category->addSlugPrototype($localizedSlug);
    }

    private function getRootCategory()
    {
        if (null === $this->rootCategoryId) {
            $channelId = $this->context->getOption('channel');
            $channel = $this->doctrineHelper->getEntityRepository(Channel::class)->find($channelId);

            $rootCategoryId = false;
            if ($channel->getTransport()->getRootCategory()) {
                $rootCategoryId = $channel->getTransport()->getRootCategory()->getId();
            }
            $this->rootCategoryId = $rootCategoryId;
        }

        if ($this->rootCategoryId) {
            return $this->doctrineHelper->getEntityReference(Category::class, $this->rootCategoryId);
        }

        return null;
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && array_key_exists($entity->getAkeneoCode(), $this->existingCategories)) {
            return $this->existingCategories[$entity->getAkeneoCode()];
        }

        $entity = $this->findExistingEntityTrait($entity, $searchContext);

        if ($entity instanceof Category) {
            $this->existingCategories[$entity->getAkeneoCode()] = $entity;
        }

        return $entity;
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Category && array_key_exists($entity->getAkeneoCode(), $this->existingCategories)) {
            return $this->existingCategories[$entity->getAkeneoCode()];
        }

        $entity = $this->findExistingEntityByIdentityFieldsTrait($entity, $searchContext);

        if ($entity instanceof Category) {
            $this->existingCategories[$entity->getAkeneoCode()] = $entity;
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
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
        $excludeCategoryFields = [
            'childCategories',
            'slugs',
            'slugPrototypes',
            'slugPrototypesWithRedirect',
        ];

        if (is_a($entityName, Category::class, true) && in_array($fieldName, $excludeCategoryFields)) {
            return true;
        }

        return parent::isFieldExcluded($entityName, $fieldName, $itemData);
    }

    public function setSlugGenerator(SlugGenerator $slugGenerator): void
    {
        $this->slugGenerator = $slugGenerator;
    }
}
