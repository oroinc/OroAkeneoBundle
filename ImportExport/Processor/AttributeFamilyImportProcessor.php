<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\AkeneoBundle\Tools\AttributeFamilyCodeGenerator;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class AttributeFamilyImportProcessor extends StepExecutionAwareImportProcessor
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $processedAttributeFamilies = [];

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        if (!empty($item['code'])) {
            $code = AttributeFamilyCodeGenerator::generate($item['code']);
            $this->processedAttributeFamilies[$code] = $code;
        }

        return parent::process($item);
    }

    public function initialize()
    {
        $this->cacheProvider->delete('attribute_family');
        $this->processedAttributeFamilies = [];
    }

    public function flush()
    {
        $this->cacheProvider->save('attribute_family', $this->processedAttributeFamilies);
        unset($this->processedAttributeFamilies);
    }
}
