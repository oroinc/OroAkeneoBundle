<?php

namespace Oro\Bundle\AkeneoBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TypeValidationLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->getDefinition('validator.builder')
            ->addMethodCall(
                'addLoader',
                [new Reference('oro_akeneo.validator.type_validation_loader')]
            );
    }
}
