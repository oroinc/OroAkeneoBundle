<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\Model\CategoryMaterializedPathModifier as Modifier;

class CategoryMaterializedPathModifier extends Modifier
{
    /** @var bool */
    protected $paused = false;

    public function pause()
    {
        $this->paused = true;
    }

    public function restore()
    {
        $this->paused = false;
    }

    public function updateMaterializedPathNested(Category $category, array $children = [])
    {
        if ($this->paused) {
            return;
        }

        parent::updateMaterializedPathNested($category, $children);
    }

    public function calculateMaterializedPath(Category $category, $scheduleForInsert = false)
    {
        if ($this->paused) {
            return;
        }

        parent::calculateMaterializedPath($category, $scheduleForInsert);
    }

    public function updateMaterializedPathQuery()
    {
        $connection = $this->doctrineHelper->getEntityManager(Category::class)->getConnection();

        $sql = 'SELECT id, parent_id, materialized_path FROM oro_catalog_category ORDER BY tree_left';
        $categoriesResult = $connection->fetchAll($sql);

        $connection->beginTransaction();
        $categories = [];
        foreach ($categoriesResult as $item) {
            $categories[$item['id']] = $item;
        }

        foreach ($categories as &$item) {
            $item['materialized_path'] = $item['id'];
            if (!empty($item['parent_id']) && !empty($categories[$item['parent_id']]['materialized_path'])) {
                $item['materialized_path'] = sprintf(
                    '%s%s%s',
                    $categories[$item['parent_id']]['materialized_path'],
                    Category::MATERIALIZED_PATH_DELIMITER,
                    $item['materialized_path']
                );
            }
            $connection->update(
                'oro_catalog_category',
                ['materialized_path' => $item['materialized_path']],
                ['id' => $item['id']]
            );
        }

        $connection->commit();
    }
}
