<?php

namespace Oro\Bundle\AkeneoBundle\EventSubscriber;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Event\Events;
use Oro\Bundle\EntityConfigBundle\Event\FieldConfigEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FieldConfigSubscriber implements EventSubscriberInterface
{
    /** @var array */
    private $configs = [
        AttributeFamily::class => ['attributeGroups' => ['full' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::CREATE_FIELD => 'updateFieldConfig',
            Events::UPDATE_FIELD => 'updateFieldConfig',
        ];
    }

    /**
     * Updates field config.
     */
    public function updateFieldConfig(FieldConfigEvent $event)
    {
        if (empty($this->configs[$event->getClassName()][$event->getFieldName()])) {
            return;
        }

        $configManager = $event->getConfigManager();
        $provider = $configManager->getProvider('importexport');
        if (!$provider) {
            return;
        }

        $config = $provider->getConfig($event->getClassName(), $event->getFieldName());
        $configs = $this->configs[$event->getClassName()][$event->getFieldName()];
        foreach ($configs as $key => $value) {
            $config->set($key, $value);
        }

        $configManager->persist($config);
    }
}
