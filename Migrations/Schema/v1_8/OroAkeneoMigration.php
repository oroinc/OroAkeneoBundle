<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_8;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoMigration implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_8';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /* Tables generation */
        $this->updateOroIntegrationTransportTable($schema);
    }

    protected function updateOroIntegrationTransportTable(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_attr_type_mapping', 'json', ['notnull' => false]);
        $table->addColumn('akeneo_family_variant_mapping', 'json', ['notnull' => false]);
    }
}
