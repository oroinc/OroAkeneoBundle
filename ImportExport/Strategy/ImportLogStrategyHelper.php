<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper as BaseImportStrategyHelper;

/**
 * Overrides base addValidationErrors method to add Akeneo data.
 */
class ImportLogStrategyHelper extends BaseImportStrategyHelper
{
    public function addValidationErrors(array $validationErrors, ContextInterface $context, $errorPrefix = null)
    {
        if (AkeneoChannel::TYPE !== $context->getOption('channelType')) {
            return parent::addValidationErrors($validationErrors, $context, $errorPrefix);
        }

        foreach ($validationErrors as $validationError) {
            $context->addError(
                $this->translator->trans(
                    'oro.akeneo.error',
                    [
                        '%error%' => $validationError,
                        '%item%' => json_encode(
                            $context->getValue('rawItemData'),
                            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                        ),
                    ]
                )
            );
        }
    }
}
