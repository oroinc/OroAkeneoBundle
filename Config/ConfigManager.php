<?php

namespace Oro\Bundle\AkeneoBundle\Config;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager as BaseManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;

class ConfigManager extends BaseManager implements ChangesAwareInterface
{
    public function hasChanges(): bool
    {
        $groupedConfigs = $this->getGroupedPersistConfigs();

        /** @var ConfigInterface[] $configs */
        foreach ($groupedConfigs as $modelKey => $configs) {
            foreach ($configs as $scope => $config) {
                $configId = $config->getId();
                $className = $configId->getClassName();
                if ($configId instanceof FieldConfigId) {
                    $fieldName = $configId->getFieldName();
                    $model = $this->getModelManager()->getFieldModel($className, $fieldName);
                } else {
                    $model = $this->getModelManager()->getEntityModel($className);
                }
                $diffData = $this->getDiff($config->getValues(), $model->toArray($scope));
                if (!empty($diffData)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getDiff($values, $originalValues)
    {
        $diff = [];
        if (empty($originalValues)) {
            foreach ($values as $code => $value) {
                $diff[$code] = [null, $value];
            }
        } else {
            foreach ($originalValues as $code => $originalValue) {
                if (array_key_exists($code, $values)) {
                    $value = $values[$code];
                    if ($originalValue != $value) {
                        $diff[$code] = [$originalValue, $value];
                    }
                } else {
                    $diff[$code] = [$originalValue, null];
                }
            }
            foreach ($values as $code => $value) {
                if (!array_key_exists($code, $originalValues)) {
                    $diff[$code] = [null, $value];
                }
            }
        }

        return $diff;
    }
}
