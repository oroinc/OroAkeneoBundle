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
}
