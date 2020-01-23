<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

use Doctrine\Common\Inflector\Inflector;

/**
 * Generates code which should start with a symbol and contain only alphabetic symbols.
 */
class Generator
{
    private const DEFAULT_LABEL_PREFIX = '';
    private const DEFAULT_LABEL_LENGTH = 22;

    private const DEFAULT_CODE_PREFIX = '';
    private const DEFAULT_CODE_LENGTH = 22;

    public static function generateLabel(
        string $value,
        int $maxLength = self::DEFAULT_LABEL_LENGTH,
        string $prefix = self::DEFAULT_LABEL_PREFIX
    ): string {
        if ($prefix && mb_strlen($value) <= $maxLength && mb_strpos($value, $prefix) === 0) {
            return $value;
        }

        $ascii = @iconv('utf-8', 'ascii//TRANSLIT', (string)$value);
        if ($ascii === false) {
            return $prefix . crc32($value);
        }

        $label = $prefix . $ascii;

        if (mb_strlen($label) <= $maxLength) {
            return $label;
        }

        return $prefix
            . mb_substr($value, 0, $maxLength - mb_strlen($prefix . '_' . crc32($value)))
            . '_'
            . crc32($value);
    }

    /**
     * @deprecated
     * @internal
     */
    public static function generateCode(
        string $value,
        int $maxLength = self::DEFAULT_CODE_LENGTH,
        string $prefix = self::DEFAULT_CODE_PREFIX
    ): string {
        $label = self::generateLabel($value, $maxLength, $prefix);

        return Inflector::tableize(Inflector::classify($label));
    }
}
