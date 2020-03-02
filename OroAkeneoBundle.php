<?php

namespace Oro\Bundle\AkeneoBundle;

use Oro\Bundle\AkeneoBundle\DependencyInjection\CompilerPass\AdditionalOptionalListenersCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OroAkeneoBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AdditionalOptionalListenersCompilerPass(), PassConfig::TYPE_AFTER_REMOVING);
    }
}
