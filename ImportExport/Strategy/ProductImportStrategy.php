<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\ImportExport\Strategy\ProductStrategy;

/**
 * Strategy to import products.
 */
class ProductImportStrategy extends ProductStrategy implements ExistingEntityAwareInterface
{
    use LocalizedFallbackValueAwareStrategyTrait;
    use StrategyRelationsTrait;
    use StrategyValidationTrait;

    /**
     * @var Product[]
     *
     * Cache existing product request in scope of a single row processing to avoid excess DB queries
     */
    private $existingProducts = [];

    public function close()
    {
        $this->reflectionProperties = [];
        $this->cachedEntities = [];

        $this->existingProducts = [];

        $this->databaseHelper->onClear();

        parent::close();
    }

    protected function beforeProcessEntity($entity)
    {
        /** @var Product $entity */
        if ($entity->isConfigurable()) {
            /** @var Product $existingProduct */
            $existingProduct = $this->findExistingEntity($entity);
            if ($existingProduct instanceof Product) {
                $entity->setStatus($existingProduct->getStatus());
            }
        }

        return parent::beforeProcessEntity($entity);
    }

    protected function afterProcessEntity($entity)
    {
        if ($entity instanceof Product && $entity->getCategory() && !$entity->getCategory()->getId()) {
            $entity->setCategory(null);

            $categoryCodes = (array)$this->fieldHelper->getItemData(
                $this->context->getValue('rawItemData'),
                'categories'
            );

            foreach (array_filter($categoryCodes) as $categoryCode) {
                $category = $this->databaseHelper->findOneBy(
                    Category::class,
                    ['akeneo_code' => $categoryCode, 'channel' => $this->context->getOption('channel')]
                );

                if ($category instanceof Category) {
                    $entity->setCategory($category);

                    break;
                }
            }
        }

        if ($entity instanceof Product && !$entity->getInventoryStatus()) {
            $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName('prod_inventory_status');
            $inventoryStatus = $this->findEntityByIdentityValues(
                $inventoryStatusClassName,
                ['id' => Product::INVENTORY_STATUS_IN_STOCK]
            );
            $entity->setInventoryStatus($inventoryStatus);
        }

        $this->existingProducts = [];

        return parent::afterProcessEntity($entity);
    }

    protected function populateOwner(Product $entity)
    {
    }

    protected function findExistingEntity($entity, array $searchContext = [])
    {
        if ($entity instanceof Product) {
            if (array_key_exists($entity->getSku(), $this->existingProducts)) {
                return $this->existingProducts[$entity->getSku()];
            }

            $entity = $this->doctrineHelper->getEntityRepository($entity)->findByCaseInsensitive(
                [
                    'sku' => $entity->getSku(),
                    'organization' => $entity->getOrganization() ?: $this->getChannel()->getOrganization(),
                ]
            );
            if (is_array($entity)) {
                $entity = array_shift($entity);
                if ($entity instanceof Product) {
                    $this->existingProducts[$entity->getSku()] = $entity;

                    return $entity;
                }

                return null;
            }

            return $entity;
        }

        if ($entity instanceof ProductUnitPrecision) {
            /** @var Product $product */
            $product = $this->findExistingEntity($entity->getProduct()) ?? $entity->getProduct();

            /** @var ProductUnitPrecision $precision */
            foreach ($product->getUnitPrecisions() as $precision) {
                if ($precision->getProductUnitCode() === $entity->getProductUnitCode()) {
                    $entity->getProduct()->setPrimaryUnitPrecision($precision);

                    return $precision;
                }
            }

            return $product->getPrimaryUnitPrecision();
        }

        return $this->findExistingEntityTrait($entity, $searchContext);
    }

    public function getExistingEntity(object $entity, array $searchContext = []): ?object
    {
        return parent::findExistingEntity($entity, $searchContext);
    }

    protected function findExistingEntityByIdentityFields($entity, array $searchContext = [])
    {
        if ($entity instanceof Product) {
            if (array_key_exists($entity->getSku(), $this->existingProducts)) {
                return $this->existingProducts[$entity->getSku()];
            }

            $entity = $this->doctrineHelper->getEntityRepository($entity)->findByCaseInsensitive(
                [
                    'sku' => $entity->getSku(),
                    'organization' => $entity->getOrganization() ?: $this->getChannel()->getOrganization(),
                ]
            );
            if (is_array($entity)) {
                $entity = array_shift($entity);
                if ($entity instanceof Product) {
                    $this->existingProducts[$entity->getSku()] = $entity;

                    return $entity;
                }

                return null;
            }

            return $entity;
        }

        if ($entity instanceof ProductUnitPrecision) {
            /** @var Product $product */
            $product = $this->findExistingEntity($entity->getProduct()) ?? $entity->getProduct();

            /** @var ProductUnitPrecision $precision */
            foreach ($product->getUnitPrecisions() as $precision) {
                if ($precision->getProductUnitCode() === $entity->getProductUnitCode()) {
                    $entity->getProduct()->setPrimaryUnitPrecision($precision);

                    return $precision;
                }
            }

            return $product->getPrimaryUnitPrecision();
        }

        return $this->findExistingEntityByIdentityFieldsTrait($entity, $searchContext);
    }

    /**
     * {@inheritdoc}
     */
    protected function importExistingEntity($entity, $existingEntity, $itemData = null, array $excludedFields = [])
    {
        // Existing enum values shouldn't be modified. Just added to entity (collection).
        if (is_a($entity, AbstractEnumValue::class, true)) {
            return;
        }

        parent::importExistingEntity($entity, $existingEntity, $itemData, $excludedFields);
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
        $excludeProductFields = [
            'variantLinks',
            'parentVariantLinks',
            'images',
            'slugs',
            'slugPrototypes',
            'slugPrototypesWithRedirect',
            'inventory_status',
        ];

        if (is_a($entityName, Product::class, true) && in_array($fieldName, $excludeProductFields)) {
            return true;
        }

        $allowedProductFields = [
            'brand',
        ];

        if (is_a($entityName, Product::class, true) && in_array($fieldName, $allowedProductFields)) {
            return false;
        }

        return parent::isFieldExcluded($entityName, $fieldName, $itemData);
    }
}
