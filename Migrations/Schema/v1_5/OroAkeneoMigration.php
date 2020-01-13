<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class OroAkeneoMigration implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_5';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /* Tables generation */
        $this->updateOroIntegrationTransportTable($schema);
    }

    /**
     * Create oro_integration_transport table
     */
    protected function updateOroIntegrationTransportTable(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_attributes_list', 'text', ['notnull' => false]);
    }
}
