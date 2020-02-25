<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

class AttributeFamilyIterator extends AbstractIterator
{
    /**
     * @var bool
     */
    private $groupsInitialized = false;

    /**
     * @var array
     */
    private $groups = [];

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $family = $this->resourceCursor->current();

        $this->setGroups($family);
        $this->setVariants($family);

        return $family;
    }

    /**
     * Set groups from API.
     */
    private function setGroups(array &$family)
    {
        if (false === $this->groupsInitialized) {
            foreach ($this->client->getAttributeGroupApi()->all(self::PAGE_SIZE) as $group) {
                $this->groups[] = $group;
            }
            $this->groupsInitialized = true;
        }

        $family['groups'] = $this->groups;
    }

    private function setVariants(array &$family)
    {
        $variants = [];
        foreach ($this->client->getFamilyVariantApi()->all($family['code'], self::PAGE_SIZE) as $variant) {
            $data['family'] = $family['code'];
            $data['variant_attribute_sets'] = $variant['variant_attribute_sets'];
            $variants[$variant['code']] = $data;
        }

        $family['variants'] = $variants;
    }
}
