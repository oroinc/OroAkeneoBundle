<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_13;

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
        if ($this->platform instanceof MySqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = concat(
        substr(MD5(original_filename), 1, 8), '-',
        substr(MD5(original_filename), 9, 4), '-',
        substr(MD5(original_filename), 13, 4), '-',
        substr(MD5(original_filename), 17, 4), '-',
        substr(MD5(original_filename), 21, 12))
WHERE parent_entity_field_name LIKE 'Akeneo_%'"
            );
        } elseif ($this->platform instanceof PostgreSqlPlatform) {
            $queries->addPostQuery(
                "UPDATE oro_attachment_file
SET uuid = MD5(original_filename)::UUID
WHERE parent_entity_field_name LIKE 'Akeneo_%';"
            );
        }
    }
}
