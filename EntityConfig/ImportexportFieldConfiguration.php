<?php

namespace Oro\Bundle\AkeneoBundle\EntityConfig;

use Oro\Bundle\EntityConfigBundle\EntityConfig\FieldConfigInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * Provides validations field config for importexport scope.
 */
class ImportexportFieldConfiguration implements FieldConfigInterface
{
    public function getSectionName(): string
    {
        return 'importexport';
    }

    public function configure(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->scalarNode('source')
                ->info('`string` source of field.')
            ->end()
            ->scalarNode('source_name')
                ->info('`string` source name of field.')
            ->end();
    }
}
