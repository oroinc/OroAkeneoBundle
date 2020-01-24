<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

class AkeneoSearchBuilder
{
    /**
     * @var array
     */
    private $requiredOptionKeys = [
        'operator',
        'value',
    ];

    /**
     * Build search filters from json.
     *
     * @param string $json
     */
    public function getFilters(string $json = null): array
    {
        if (!$this->isJSON($json)) {
            return [];
        }

        $searchBuilder = new \Akeneo\Pim\ApiClient\Search\SearchBuilder();
        $productFilter = json_decode($json, true);

        foreach ($productFilter as $filterName => $options) {
            foreach ($options as $option) {
                if ($this->isValidOption($option)) {
                    $searchBuilder
                        ->addFilter(
                            $filterName,
                            $option['operator'],
                            $option['value'],
                            array_merge(
                                (isset($option['scope']) ? ['scope' => $option['scope']] : []),
                                (isset($option['locale']) ? ['locale' => $option['locale']] : []),
                                (isset($option['locales']) ? ['locales' => $option['locales']] : [])
                            )
                        );
                }
            }
        }

        return $searchBuilder->getFilters();
    }

    /**
     * @param $string
     */
    public function isJSON($string): bool
    {
        return is_string($string) && is_array(json_decode($string, true)) && (JSON_ERROR_NONE == json_last_error());
    }

    private function isValidOption(array $option): bool
    {
        return is_array($option) &&
            count(array_intersect_key(array_flip($this->requiredOptionKeys), $option)) === count(
                $this->requiredOptionKeys
            );
    }
}
