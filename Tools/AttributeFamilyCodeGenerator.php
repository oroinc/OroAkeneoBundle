<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class AttributeFamilyCodeGenerator
{
    /**
     * @param string $value
     *
     * @return string
     */
    public static function generate(string $value): string
    {
        return sprintf('Akeneo_%s', $value);
    }
}
