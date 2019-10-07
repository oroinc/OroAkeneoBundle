<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class AttributeFamilyCodeGenerator
{
    /**
     * @param string $value
     * @param string $prefix
     *
     * @return string
     */
    public static function generate(string $value, $prefix = 'Akeneo_'): string
    {
        return sprintf('%s%s', $prefix, $value);
    }
}
