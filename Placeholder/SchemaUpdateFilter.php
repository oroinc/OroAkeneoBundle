<?php

namespace Oro\Bundle\AkeneoBundle\Placeholder;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Helper\EntityConfigProviderHelper;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

/**
 * Checks if schema update should be applicable.
 */
class SchemaUpdateFilter
{
    const ACTION_NAME = 'oro.entity_extend.entity_config.extend.field.layout_action.update_schema';

    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * @var EntityConfigProviderHelper
     */
    private $entityConfigProviderHelper;

    /**
     * SchemaUpdateFilter constructor.
     */
    public function __construct(ConfigManager $configManager, EntityConfigProviderHelper $entityConfigProviderHelper)
    {
        $this->configManager = $configManager;
        $this->entityConfigProviderHelper = $entityConfigProviderHelper;
    }

    /**
     * Check if schema update button is applicable.
     *
     * @param object $entity
     *
     * @return bool
     */
    public function isApplicable($entity, string $entityConfigModelClass)
    {
        if (false === is_a($entity, Channel::class) || AkeneoChannel::TYPE !== $entity->getType()) {
            return false;
        }

        $entityConfigModel = $this->configManager->getConfigEntityModel($entityConfigModelClass);
        list($actions) = $this->entityConfigProviderHelper->getLayoutParams($entityConfigModel);

        return $this->containsSchemaUpdateAction($actions);
    }

    /**
     * Check if actions array contains schema update.
     *
     * @param array $actions
     *
     * @return bool
     */
    private function containsSchemaUpdateAction($actions)
    {
        foreach ($actions as $action) {
            if (self::ACTION_NAME === $action['name']) {
                return true;
            }
        }

        return false;
    }
}
