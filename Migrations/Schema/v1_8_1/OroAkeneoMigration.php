<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_8_1;

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
        return 'v1_8_1';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->updateOroIntegrationTransportTable($schema);
    }

    /**
     * Create oro_integration_transport table
     */
    protected function updateOroIntegrationTransportTable(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');

        if (!$table->hasColumn('akeneo_product_unit_attribute')) {
            $table->addColumn('akeneo_product_unit_attribute', 'string', ['notnull' => false, 'length' => 255]);
        }

        if (!$table->hasColumn('akeneo_unit_precision_attr')) {
            $table->addColumn('akeneo_unit_precision_attr', 'string', ['notnull' => false, 'length' => 255]);
        }
    }
}
