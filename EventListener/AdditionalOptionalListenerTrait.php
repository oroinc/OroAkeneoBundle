<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

trait AdditionalOptionalListenerTrait
{
    protected $enabled = true;

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
