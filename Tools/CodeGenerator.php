<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class CodeGenerator
{
    /**
     * @param string $value
     * @param int $maxLength
     *
     * @return string
     */
    public static function generate(string $value, int $maxLength = 22): string
    {
        if (mb_strlen($value) <= $maxLength && mb_strpos($value, 'Akeneo_') === 0) {
            return $value;
        }

        $ascii = @iconv('utf-8', 'ascii//TRANSLIT', (string)$value);
        if ($ascii === false) {
            return sprintf('Akeneo_%s', crc32($value));
        }

        $label = sprintf('Akeneo_%s', $ascii);

        if (mb_strlen($label) <= $maxLength) {
            return $label;
        }

        return sprintf('Akeneo_%s', crc32($value));
    }
}
