<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

class AdditionalOptionalListenerManager
{
    /**
     * @var AdditionalOptionalListenerInterface[]
     */
    private $additionalOptionalListeners = [];

    public function addAdditionalOptionalListener(
        AdditionalOptionalListenerInterface $additionalOptionalListener
    ): void {
        $this->additionalOptionalListeners[] = $additionalOptionalListener;
    }

    public function disableListeners(): void
    {
        foreach ($this->additionalOptionalListeners as $additionalOptionalListener) {
            $additionalOptionalListener->disable();
        }
    }

    public function enableListeners(): void
    {
        foreach ($this->additionalOptionalListeners as $additionalOptionalListener) {
            $additionalOptionalListener->enable();
        }
    }
}
