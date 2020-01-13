<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

class AttributeTypeConverter
{
    /**
     * @var
     */
    const TYPE_MAPPING = [
        'pim_catalog_identifier' => 'string',
        'pim_catalog_metric' => 'string',
        'pim_catalog_boolean' => 'boolean',
        'pim_catalog_number' => 'float',
        'pim_catalog_text' => 'manyToMany',
        'pim_catalog_textarea' => 'manyToMany',
        'pim_catalog_file' => 'file',
        'pim_catalog_date' => 'manyToMany',
        'pim_catalog_simpleselect' => 'enum',
        'pim_catalog_multiselect' => 'multiEnum',
    ];

    /**
     * @return string
     */
    public static function convert(string $type): ?string
    {
        return self::TYPE_MAPPING[mb_strtolower($type)] ?? null;
    }
}
