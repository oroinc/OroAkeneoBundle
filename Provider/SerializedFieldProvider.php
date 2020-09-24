<?php

namespace Oro\Bundle\AkeneoBundle\Provider;

use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\ProductBundle\Provider\SerializedFieldProvider as BaseSerializedFieldProvider;

class SerializedFieldProvider extends BaseSerializedFieldProvider
{
    /** @var BaseSerializedFieldProvider */
    private $serializedFieldProvider;

    /** @var ConfigProvider */
    private $configProvider;

    public function __construct(BaseSerializedFieldProvider $serializedFieldProvider, ConfigProvider $configProvider)
    {
        $this->serializedFieldProvider = $serializedFieldProvider;
        $this->configProvider = $configProvider;
    }

    public function isSerialized(FieldConfigModel $fieldConfigModel)
    {
        $type = $fieldConfigModel->getType();

        $config = $this->configProvider->getConfig(
            $fieldConfigModel->getEntity()->getClassName(),
            $fieldConfigModel->getFieldName()
        );

        if (in_array($type, ['string', 'boolean']) && $config->is('source', 'akeneo')) {
            return false;
        }

        return $this->serializedFieldProvider->isSerialized($fieldConfigModel);
    }

    public function isSerializedByData(FieldConfigModel $fieldConfigModel, array $data = [])
    {
        $type = $fieldConfigModel->getType();

        $config = $this->configProvider->getConfig(
            $fieldConfigModel->getEntity()->getClassName(),
            $fieldConfigModel->getFieldName()
        );

        if (in_array($type, ['string', 'boolean']) && $config->is('source', 'akeneo')) {
            return false;
        }

        return $this->serializedFieldProvider->isSerializedByData($fieldConfigModel, $data);
    }

    public function getSerializableTypes()
    {
        return $this->serializedFieldProvider->getSerializableTypes();
    }

    protected function isSerializableType(FieldConfigModel $fieldConfigModel)
    {
        return $this->serializedFieldProvider->isSerializableType($fieldConfigModel);
    }

    protected function getRequiredPropertyValues()
    {
        return $this->serializedFieldProvider->getRequiredPropertyValues();
    }
}
