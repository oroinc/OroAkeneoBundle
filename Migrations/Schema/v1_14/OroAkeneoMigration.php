<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_14;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Extension\DatabasePlatformAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Extension\DatabasePlatformAwareTrait;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoMigration implements Migration, DatabasePlatformAwareInterface
{
    use DatabasePlatformAwareTrait;

    public function up(Schema $schema, QueryBag $queries)
    {
        $queries->addPostQuery(
            "UPDATE oro_attachment_file
SET owner_user_id = (SELECT default_user_owner_id FROM oro_integration_channel WHERE type = 'oro_akeneo' LIMIT 1)
WHERE owner_user_id IS NULL
  AND parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\Product'
  AND parent_entity_field_name LIKE 'Akeneo%';"
        );

        if ($this->platform instanceof MySqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = UUID();
WHERE parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\Product'
AND parent_entity_field_name LIKE 'Akeneo%';"
            );
        } elseif ($this->platform instanceof PostgreSqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = uuid_generate_v4()
WHERE parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\Product'
AND parent_entity_field_name LIKE 'Akeneo%';"
            );
        }

        $queries->addPostQuery(
            "UPDATE oro_attachment_file
SET owner_user_id = (SELECT default_user_owner_id FROM oro_integration_channel WHERE type = 'oro_akeneo' LIMIT 1)
WHERE owner_user_id IS null
    AND parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\ProductImage'
    AND parent_entity_field_name = 'image';"
        );

        if ($this->platform instanceof MySqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = UUID();
WHERE parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\ProductImage'
AND parent_entity_field_name = 'image';"
            );
        } elseif ($this->platform instanceof PostgreSqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = uuid_generate_v4()
WHERE parent_entity_class = 'Oro\Bundle\ProductBundle\Entity\ProductImage'
AND parent_entity_field_name = 'image';"
            );
        }

        $table = $schema->getTable('oro_attachment_file');
        $table->getColumn('parent_entity_class')->setLength(255);
        $table->addIndex(['parent_entity_class', 'parent_entity_id'], 'oro_akeneo_file_parent_index');
    }
}
