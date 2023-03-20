<?php

namespace Oro\Bundle\AkeneoBundle\Async;

use Oro\Component\MessageQueue\Topic\AbstractTopic;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Topics extends AbstractTopic
{
    const IMPORT_PRODUCTS = 'oro.integration.akeneo.product';

    public static function getName(): string
    {
        return self::IMPORT_PRODUCTS;
    }

    public static function getDescription(): string
    {
        return 'Synchronizes Akeneo products batch.';
    }

    public function configureMessageBody(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefined([
                'integrationId',
                'connector',
                'connector_parameters',
                'jobId',
            ])
            ->setRequired([
                'integrationId',
                'jobId',
            ])
            ->setDefaults([
                'connector_parameters' => [],
            ])
            ->addAllowedTypes('integrationId', ['string', 'int'])
            ->addAllowedTypes('connector', ['null', 'string'])
            ->addAllowedTypes('connector_parameters', 'array')
            ->addAllowedTypes('jobId', ['string', 'int']);
    }
}
