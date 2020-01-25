<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class AttributeFamilyCodeGenerator
{
    public static function generate(string $value, string $prefix = 'Akeneo_'): string
    {
        return sprintf('%s%s', $prefix, $value);
    }
}
