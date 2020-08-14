<?php

namespace Oro\Bundle\AkeneoBundle\Layout\DataProvider;

use Oro\Bundle\AttachmentBundle\Layout\DataProvider\FileApplicationsDataProvider as BaseFileApplicationsDataProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

class FileApplicationsDataProvider extends BaseFileApplicationsDataProvider
{
    /** @var BaseFileApplicationsDataProvider */
    private $applicationsDataProvider;

    /** @var ConfigProvider */
    private $configProvider;

    public function __construct(
        BaseFileApplicationsDataProvider $applicationsDataProvider,
        ConfigProvider $configProvider
    ) {
        $this->applicationsDataProvider = $applicationsDataProvider;
        $this->configProvider = $configProvider;
    }

    public function isValidForField(string $className, string $fieldName): bool
    {
        $isValidForField = $this->applicationsDataProvider->isValidForField($className, $fieldName);
        if (!$isValidForField) {
            return $isValidForField;
        }

        if (!$this->configProvider->hasConfig($className, $fieldName)) {
            return false;
        }

        $config = $this->configProvider->getConfig($className, $fieldName);

        return $config->get('visible');
    }
}
