<?php

namespace Oro\Bundle\AkeneoBundle\Migrations\Schema\v1_9;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigFieldValueQuery;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class OroAkeneoMigration implements Migration, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function up(Schema $schema, QueryBag $queries)
    {
        $queries->addPostQuery(
            new UpdateEntityConfigFieldValueQuery(
                AttributeGroup::class,
                'akeneo_code',
                'importexport',
                'identity',
                false
            )
        );
        $queries->addPostQuery(
            new UpdateEntityConfigFieldValueQuery(
                Category::class,
                'akeneo_code',
                'importexport',
                'identity',
                false
            )
        );
    }
}
