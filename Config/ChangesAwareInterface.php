<?php

namespace Oro\Bundle\AkeneoBundle\Config;

interface ChangesAwareInterface
{
    public function hasChanges(): bool;
}
