<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class CategoryImportProcessor extends StepExecutionAwareImportProcessor
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $processedIds;

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        $this->processedIds[$item['code']] = $item['parent'] ?? null;

        return parent::process($item);
    }

    public function initialize()
    {
        $this->cacheProvider->delete('category');
        $this->processedIds = [];
    }

    public function flush()
    {
        $this->cacheProvider->save('category', $this->processedIds);
        $this->processedIds = null;
    }
}
