<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

/**
 * Generates field name which should start with a symbol and contain only alphabetic symbols, underscore and numbers.
 */
class FieldConfigModelFieldNameGenerator
{
    /**
     * @param string $value
     *
     * @return string
     */
    public static function generate(string $value): string
    {
        $value = sprintf('Akeneo_%s', $value);

        if (mb_strlen($value) < 23) {
            return $value;
        }

        return mb_substr($value, 0, 11).'_'.crc32($value);
    }
}
