<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_7;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoMigration implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->updateOroIntegrationTransportTable($schema);
    }

    protected function updateOroIntegrationTransportTable(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_product_unit_attribute', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_unit_precision_attr', 'string', ['notnull' => false, 'length' => 255]);
    }
}
