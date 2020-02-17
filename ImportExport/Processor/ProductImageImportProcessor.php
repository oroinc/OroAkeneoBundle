<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Oro\Bundle\IntegrationBundle\ImportExport\Processor\StepExecutionAwareImportProcessor;

class ProductImageImportProcessor extends StepExecutionAwareImportProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        if ($this->dataConverter) {
            $item = $this->dataConverter->convertToImportFormat($item, false);
        }

        $object = $this->serializer->deserialize(
            $item,
            $this->getEntityName(),
            null,
            array_merge(
                $this->context->getConfiguration(),
                [
                    'entityName' => $this->getEntityName(),
                ]
            )
        );

        if ($this->strategy) {
            $object = $this->strategy->process($object);
        }

        return $object ?: null;
    }
}
