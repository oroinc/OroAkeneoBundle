<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroAkeneoBundleInstaller implements Installation, ExtendExtensionAwareInterface
{
    /**
     * @var ExtendExtension
     */
    protected $extendExtension;

    /**
     * @var array
     */
    protected $options = [
        'extend'       => [
            'origin'        => ExtendScope::OWNER_CUSTOM,
            'owner'         => ExtendScope::OWNER_CUSTOM,
            'state'         => ExtendScope::STATE_NEW,
            'is_serialized' => false,
            'is_extend'     => true,
        ],
        'datagrid'     => [
            'is_visible'  => 0,
            'show_filter' => 0,
            'order'       => null,
        ],
        'form'         => [
            'is_enabled' => false,
        ],
        'view'         => [
            'is_displayable' => false,
        ],
        'importexport' => [
            'identity' => true,
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_7';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /* Tables generation */
        $this->createOroAkeneoLocaleTable($schema);
        $this->updateIntegrationTransportTable($schema);

        /* Foreign keys generation */
        $this->addOroAkeneoLocaleForeignKeys($schema);
        $this->addOroIntegrationTransportForeignKeys($schema);

        $this->updateCategoryTable($schema);
        $this->updateAttributeFamilyTable($schema);
        $this->updateAttributeGroupTable($schema);
    }

    /**
     * Create oro_akeneo_locale table.
     */
    protected function createOroAkeneoLocaleTable(Schema $schema)
    {
        $table = $schema->createTable('oro_akeneo_locale');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('akeneosettings_id', 'integer', ['notnull' => false]);
        $table->addColumn('code', 'string', ['length' => 200]);
        $table->addColumn('locale', 'string', ['notnull' => false, 'length' => 10]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['akeneosettings_id'], 'idx_fcc21132955cd68c', []);
    }

    /**
     * Update oro_integration_transport table.
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function updateIntegrationTransportTable(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('akeneo_client_id', 'string', ['notnull' => false, 'length' => 100]);
        $table->addColumn('akeneo_secret', 'string', ['notnull' => false, 'length' => 100]);
        $table->addColumn('akeneo_url', 'string', ['notnull' => false, 'length' => 100]);
        $table->addColumn('akeneo_sync_products', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_product_unit_attribute', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_unit_precision_attr', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_channels', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('akeneo_username', 'string', ['notnull' => false, 'length' => 200]);
        $table->addColumn('akeneo_password', 'string', ['notnull' => false, 'length' => 200]);
        $table->addColumn('akeneo_token', 'string', ['notnull' => false, 'length' => 200]);
        $table->addColumn('akeneo_refresh_token', 'string', ['notnull' => false, 'length' => 200]);
        $table->addColumn(
            'akeneo_token_expiry_date_time',
            'datetime',
            ['notnull' => false, 'comment' => '(DC2Type:datetime)']
        );
        $table->addColumn('akeneo_locales_list', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('akeneo_currencies', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('akeneo_active_currencies', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('akeneo_active_channel', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('akeneo_acl_voter_enabled', 'boolean', ['notnull' => false]);
        $table->addColumn('akeneo_product_filter', 'text', ['notnull' => false]);
        $table->addColumn('akeneo_attributes_list', 'text', ['notnull' => false]);
        $table->addColumn('rootcategory_id', 'integer', ['notnull' => false]);
        $table->addColumn('pricelist_id', 'integer', ['notnull' => false]);
        $table->addColumn('akeneo_attributes_image_list', 'text', ['notnull' => false]);
        $table->addColumn('akeneo_merge_image_to_parent', 'boolean', ['notnull' => false]);
        $table->addIndex(['rootcategory_id'], 'idx_d7a389a852d2453c', []);
        $table->addIndex(['pricelist_id'], 'idx_d7a389a846b960c4', []);
    }

    /**
     * Add oro_akeneo_locale foreign keys.
     */
    protected function addOroAkeneoLocaleForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_akeneo_locale');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['akeneosettings_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => null]
        );
    }

    /**
     * Add oro_integration_transport foreign keys.
     */
    protected function addOroIntegrationTransportForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_catalog_category'),
            ['rootcategory_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_price_list'),
            ['pricelist_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
    }

    /**
     * Add channel & akeneo code to category table.
     */
    protected function updateCategoryTable(Schema $schema)
    {
        $this->extendExtension->addManyToOneRelation(
            $schema,
            'oro_catalog_category',
            'channel',
            'oro_integration_channel',
            'name',
            [
                'form'   => [
                    'is_enabled' => false,
                ],
                'view'   => [
                    'is_displayable' => false,
                ],
                'entity' => [
                    'label' => 'oro.akeneo.category.channel.label',
                ],
                'extend' => [
                    'owner' => ExtendScope::OWNER_CUSTOM,
                ],
            ]
        );
        $options = array_merge(
            $this->options,
            [
                'entity' => [
                    'label' => 'oro.akeneo.category.akeneo_code.label',
                ],
            ]
        );
        $table = $schema->getTable('oro_catalog_category');
        $table->addColumn(
            'akeneo_code',
            'string',
            [
                'notnull'     => false,
                'oro_options' => $options,
            ]
        );
    }

    /**
     * Add channel to attribute family table.
     */
    protected function updateAttributeFamilyTable(Schema $schema)
    {
        $this->extendExtension->addManyToOneRelation(
            $schema,
            'oro_attribute_family',
            'channel',
            'oro_integration_channel',
            'name',
            [
                'form'   => [
                    'is_enabled' => false,
                ],
                'view'   => [
                    'is_displayable' => false,
                ],
                'entity' => [
                    'label' => 'oro.akeneo.attribute_family.channel.label',
                ],
                'extend' => [
                    'owner' => ExtendScope::OWNER_CUSTOM,
                ],
            ]
        );
    }

    /**
     * Add akeneo_code to attribute group table.
     *
     * @throws SchemaException
     */
    protected function updateAttributeGroupTable(Schema $schema)
    {
        $options = array_merge(
            $this->options,
            [
                'entity' => [
                    'label' => 'oro.akeneo.attribute_group.akeneo_code.label',
                ],
            ]
        );
        $table = $schema->getTable('oro_attribute_group');
        $table->addColumn(
            'akeneo_code',
            'string',
            [
                'notnull'     => false,
                'oro_options' => $options,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }
}
