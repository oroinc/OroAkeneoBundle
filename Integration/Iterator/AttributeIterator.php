<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

class AttributeIterator extends AbstractIterator
{
    /**
     * @var
     */
    const OPTION_TYPES = [
        'pim_catalog_simpleselect',
        'pim_catalog_multiselect',
    ];

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $attribute = $this->resourceCursor->current();

        $this->setOptions($attribute);

        return $attribute;
    }

    /**
     * Get attribute options from API.
     *
     * @param array $attribute
     *
     * @return array
     */
    private function setOptions(array &$attribute)
    {
        if (false === in_array($attribute['type'], self::OPTION_TYPES)) {
            return $attribute;
        }

        $attribute['options'] = [];

        foreach ($this->client->getAttributeOptionApi()->all($attribute['code'], self::PAGE_SIZE) as $option) {
            $attribute['options'][] = $option;
        }

        usort(
            $attribute['options'],
            function ($a, $b) {
                if ($a['sort_order'] == $b['sort_order']) {
                    return 0;
                }

                return ($a['sort_order'] < $b['sort_order']) ? -1 : 1;
            }
        );
    }
}
