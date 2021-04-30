<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_15;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigFieldValueQuery;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
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
        /** @var ConfigProvider $importExportProvider */
        $importExportProvider = $this->container
            ->get('oro_entity_config.config_manager')
            ->getProvider('importexport');

        /** @var ConfigProvider $viewExportProvider */
        $viewExportProvider = $this->container
            ->get('oro_entity_config.config_manager')
            ->getProvider('view');

        /** @var ConfigProvider $formExportProvider */
        $formExportProvider = $this->container
            ->get('oro_entity_config.config_manager')
            ->getProvider('form');

        foreach ($importExportProvider->getConfigs(Product::class) as $field) {
            if ('akeneo' !== $field->get('source')) {
                continue;
            }

            $fieldName = $field->getId()->getFieldName();
            $queries->addPostQuery(
                new UpdateEntityConfigFieldValueQuery(
                    Product::class,
                    $fieldName,
                    'frontend',
                    'is_displayable',
                    $viewExportProvider
                        ->getConfig(Product::class, $fieldName)
                        ->get('is_displayable', false, false)
                )
            );

            $queries->addPostQuery(
                new UpdateEntityConfigFieldValueQuery(
                    Product::class,
                    $fieldName,
                    'frontend',
                    'is_editable',
                    $formExportProvider
                        ->getConfig(Product::class, $fieldName)
                        ->get('is_enabled', false, false)
                )
            );
        }
    }
}
