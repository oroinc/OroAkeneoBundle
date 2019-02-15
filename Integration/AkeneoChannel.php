<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Oro\Bundle\IntegrationBundle\Provider\ChannelInterface;
use Oro\Bundle\IntegrationBundle\Provider\IconAwareIntegrationInterface;

class AkeneoChannel implements ChannelInterface, IconAwareIntegrationInterface
{
    const TYPE = 'oro_akeneo';

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'oro.akeneo.integration.channel.label';
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon()
    {
        return 'bundles/oroakeneo/img/akeneo-icon.svg';
    }
}
