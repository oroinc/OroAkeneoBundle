<?php

namespace Oro\Bundle\AkeneoBundle\DependencyInjection\CompilerPass;

use Oro\Bundle\AkeneoBundle\EventListener\AttributesGridListener;
use Oro\Bundle\AkeneoBundle\Tools\AttributeEntityGeneratorExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class EnterprisePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('oro_entity_config_pro.grid.entity_generator.extension.attribute')) {
            $definition = new Definition(AttributeEntityGeneratorExtension::class);
            $definition
                ->setDecoratedService('oro_entity_config_pro.grid.entity_generator.extension.attribute')
                ->setTags(
                    $container
                        ->getDefinition('oro_entity_config_pro.grid.entity_generator.extension.attribute')
                        ->getTags()
                );
            $container->setDefinition('oro_akeneo.tools.attribute_entity_generator_extension', $definition);
        }

        if ($container->hasDefinition('oro_entity_config_pro.listener.attributes_grid_listener')) {
            $definition = new Definition(AttributesGridListener::class);
            $definition->setArguments(
                [
                    new Reference('oro_security.token_accessor'),
                    new Reference('oro_entity.doctrine_helper'),
                ]
            );
            $definition
                ->addTag(
                    'kernel.event_listener',
                    [
                        'event' => 'oro_datagrid.datagrid.build.after.attributes-grid',
                        'method' => 'onBuildAfter',
                        'priority' => -255,
                    ]
                )
                ->addTag(
                    'kernel.event_listener',
                    [
                        'event' => 'oro_datagrid.orm_datasource.result.after.attributes-grid',
                        'method' => 'onResultAfter',
                    ]
                );
            $container->setDefinition('oro_akeneo.event_listener.attributes_grid_listener', $definition);
        }
    }
}
