<?php

namespace Oro\Bundle\AkeneoBundle\DependencyInjection\CompilerPass;

use Oro\Bundle\AkeneoBundle\EventListener\AdditionalOptionalListenerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collect additional optional listeners
 */
class AdditionalOptionalListenersCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $listeners = array_keys(
            array_merge(
                $container->findTaggedServiceIds('kernel.event_listener'),
                $container->findTaggedServiceIds('kernel.event_subscriber'),
                $container->findTaggedServiceIds('doctrine.orm.entity_listener'),
                $container->findTaggedServiceIds('doctrine.event_listener'),
                $container->findTaggedServiceIds('doctrine.event_subscriber')
            )
        );

        $definition = $container->getDefinition('oro_akeneo.event_listener.additional_optional_listeners_manager');
        foreach ($listeners as $listener) {
            $className = $container->getDefinition($listener)->getClass();
            if (is_a($className, AdditionalOptionalListenerInterface::class, true)) {
                $definition->addMethodCall('addAdditionalOptionalListener', [new Reference($listener)]);
            }
        }
    }
}
