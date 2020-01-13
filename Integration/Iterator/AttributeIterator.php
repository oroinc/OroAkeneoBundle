<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Psr\Log\LoggerInterface;

class AttributeIterator extends AbstractIterator
{
    /**
     * @var array
     */
    private $attributesFilter = [];

    /**
     * AttributeIterator constructor.
     *
     * @param array $attributesFilter
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimEnterpriseClientInterface $client,
        LoggerInterface $logger,
        $attributesFilter = []
    ) {
        $this->attributesFilter = $attributesFilter;

        parent::__construct($resourceCursor, $client, $logger);
    }

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
        $attribute = $this->filter();

        if (null === $attribute) {
            return null;
        }

        $this->setOptions($attribute);

        return $attribute;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->resourceCursor->valid();
    }

    /**
     * Get attribute options from API.
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

    /**
     * @return array|null
     */
    private function filter()
    {
        do {
            $attribute = $this->resourceCursor->current();

            if (!empty($this->attributesFilter) && !in_array($attribute['code'], $this->attributesFilter)) {
                $this->next();

                if (!$this->valid()) {
                    return null;
                }
            } else {
                break;
            }
        } while ($this->valid());

        return $attribute;
    }
}
