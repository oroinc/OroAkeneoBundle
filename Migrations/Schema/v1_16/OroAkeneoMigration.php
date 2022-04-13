<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_16;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoMigration implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_conf_product_filter', 'text', ['notnull' => false]);

        $queries->addPostQuery('UPDATE oro_integration_transport SET akeneo_conf_product_filter = akeneo_product_filter ' .
            'WHERE akeneo_product_filter IS NOT NULL;');
    }
}
