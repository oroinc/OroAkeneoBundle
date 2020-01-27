<?php

namespace Oro\Bundle\AkeneoBundle\Config;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager as BaseManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;

class ConfigManager extends BaseManager
{
    /**
     * @return bool
     */
    public function canSkipFlush()
    {
        $groupedConfigs = $this->getGroupedPersistConfigs();

        $anythingChanged = false;
        /** @var ConfigInterface[] $configs */
        foreach ($groupedConfigs as $modelKey => $configs) {
            foreach ($configs as $scope => $config) {
                $configId = $config->getId();
                $className = $configId->getClassName();
                $propertyConfig = $this->getPropertyConfig($scope);
                if ($configId instanceof FieldConfigId) {
                    $fieldName = $configId->getFieldName();
                    $model = $this->modelManager->getFieldModel($className, $fieldName);
                } else {
                    $model = $this->modelManager->getEntityModel($className);
                }
                $diffData = $this->getDiff($config->getValues(), $model->toArray($scope));
                if (!empty($diffData)) {
                    return false;
                }
            }
        }

        return true;
    }
}
