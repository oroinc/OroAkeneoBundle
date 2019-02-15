<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Entity\Repository\EnumValueRepository;
use Oro\Bundle\EntityExtendBundle\Tools\EnumSynchronizer as BaseEnumSynchronizer;

class EnumSynchronizer extends BaseEnumSynchronizer
{
    /**
     * {@inheritdoc}
     *
     * Override because by default this method generates ID from option label name.
     * Akeneo provides only enum option ID when importing products from API.
     * This ID can be different from the one generated from the label, so
     * the enum options should be created from the supplied Akeneo ID instead.
     */
    protected function processValues(array $values, array $options, EntityManager $em, EnumValueRepository $enumRepo)
    {
        $ids = [];
        /** @var AbstractEnumValue[] $changes */
        $changes = [];
        /** @var AbstractEnumValue[] $removes */
        $removes = [];
        foreach ($values as $value) {
            $id = $value->getId();
            $optionKey = $this->getEnumOptionKey($id, $options);
            if (null !== $optionKey) {
                $ids[] = $id;
                if ($this->setEnumValueProperties($value, $options[$optionKey])) {
                    $changes[] = $value;
                }
                unset($options[$optionKey]);
            } else {
                $em->remove($value);
                $removes[] = $value;
            }
        }
        if ($removes) {
            $em->flush($removes);
        }

        foreach ($options as $option) {
            $value = $enumRepo->createEnumValue(
                $option['label'],
                $option['priority'],
                $option['is_default'],
                $option['id']
            );
            $em->persist($value);
            $changes[] = $value;
        }

        return $changes;
    }
}
