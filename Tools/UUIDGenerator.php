<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class UUIDGenerator
{
    public static function generate(string $filename): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(md5(basename($filename)), 4));
    }
}
