<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_12;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoMigration implements Migration, ExtendExtensionAwareInterface
{
    /** @var ExtendExtension */
    protected $extendExtension;

    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_brand_reference_code', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_brand_mapping', 'text', ['notnull' => false]);

        $this->extendExtension->addManyToOneRelation(
            $schema,
            'oro_brand',
            'channel',
            'oro_integration_channel',
            'name',
            [
                'extend' => ['owner' => ExtendScope::OWNER_CUSTOM],
                'datagrid' => ['is_visible' => 0, 'show_filter' => 0, 'order' => null],
                'form' => ['is_enabled' => false],
                'view' => ['is_displayable' => false],
                'importexport' => ['identity' => false],
                'entity' => ['label' => 'oro.akeneo.brand.channel.label'],
            ]
        );
        $table = $schema->getTable('oro_brand');
        $table->addColumn(
            'akeneo_code',
            'string',
            [
                'notnull' => false,
                'oro_options' => [
                    'extend' => [
                        'origin' => ExtendScope::OWNER_CUSTOM,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => ExtendScope::STATE_NEW,
                        'is_serialized' => false,
                        'is_extend' => true,
                    ],
                    'datagrid' => ['is_visible' => 0, 'show_filter' => 0, 'order' => null],
                    'form' => ['is_enabled' => false],
                    'view' => ['is_displayable' => false],
                    'importexport' => ['identity' => false],
                    'entity' => ['label' => 'oro.akeneo.brand.akeneo_code.label'],
                ],
            ]
        );
    }

    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }
}
