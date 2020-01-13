<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class AttributeFamilyCodeGenerator
{
    public static function generate(string $value): string
    {
        return sprintf('Akeneo_%s', $value);
    }
}
