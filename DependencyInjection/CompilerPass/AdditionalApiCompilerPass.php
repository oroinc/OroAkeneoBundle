<?php

namespace Oro\Bundle\AkeneoBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AdditionalApiCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('oro_akeneo.client_builder');
        foreach ($container->findTaggedServiceIds('oro_akeneo.api') as $serviceId => $additionalApiService) {
            $definition->addMethodCall('addApi', [new Reference($serviceId)]);
        }
    }
}
