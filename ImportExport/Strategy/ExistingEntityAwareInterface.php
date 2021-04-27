<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Strategy;

interface ExistingEntityAwareInterface
{
    public function getExistingEntity(object $entity): ?object;
}
