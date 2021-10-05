<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_8;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigFieldValueQuery;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class OroAkeneoMigration implements Migration, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function up(Schema $schema, QueryBag $queries)
    {
        $fields = $this->container
            ->get('oro_entity.helper.field_helper')
            ->getEntityFields(Product::class, EntityFieldProvider::OPTION_WITH_RELATIONS);

        $importExportProvider = $this->container
            ->get('oro_entity_config.config_manager')
            ->getProvider('importexport');

        foreach ($fields as $field) {
            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);
            if ('akeneo' !== $importExportConfig->get('source')) {
                continue;
            }

            $queries->addPostQuery(
                new UpdateEntityConfigFieldValueQuery(Product::class, $field['name'], 'view', 'is_displayable', false)
            );
            $queries->addPostQuery(
                new UpdateEntityConfigFieldValueQuery(Product::class, $field['name'], 'form', 'is_enabled', false)
            );
        }
    }
}
