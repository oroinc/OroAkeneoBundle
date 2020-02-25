<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\AkeneoBundle\Tools\AttributeFamilyCodeGenerator;
use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class AttributeFamilyImportProcessor extends StepExecutionAwareImportProcessor
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $processedAttributeFamilies = [];

    /** @var array */
    private $familyVariants = [];

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        if (!empty($item['code'])) {
            $code = AttributeFamilyCodeGenerator::generate($item['code']);
            $this->processedAttributeFamilies[$code] = $code;
        }

        if (!empty($item['variants'])) {
            $this->familyVariants = array_merge($this->familyVariants, $item['variants']);
            unset($item['variants']);
        }

        return parent::process($item);
    }

    public function initialize()
    {
        $this->cacheProvider->delete('attribute_family');
        $this->cacheProvider->delete('attribute_familyVariants');
        $this->processedAttributeFamilies = [];
        $this->familyVariants = [];
    }

    public function flush()
    {
        $this->cacheProvider->save('attribute_family', $this->processedAttributeFamilies);
        $this->cacheProvider->save('attribute_familyVariants', $this->familyVariants);
        $this->processedAttributeFamilies = null;
        $this->familyVariants = null;
    }
}
