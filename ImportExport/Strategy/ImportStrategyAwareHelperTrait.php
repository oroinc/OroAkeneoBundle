<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

/**
 * Allows set import strategy helper with setter injection.
 */
trait ImportStrategyAwareHelperTrait
{
    /**
     * @param ImportStrategyHelper $strategyHelper
     */
    public function setImportStrategyHelper(ImportStrategyHelper $strategyHelper): void
    {
        $this->strategyHelper = $strategyHelper;
    }
}
