<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareInterface;
use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderAwareTrait;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class AttributeFamilyStatusProcessor implements ProcessorInterface, MemoryCacheProviderAwareInterface
{
    use MemoryCacheProviderAwareTrait;

    /** @var ManagerRegistry */
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        if (!$item instanceof AttributeFamily) {
            return null;
        }

        if ($this->memoryCacheProvider->get('attribute_family_' . $item->getCode())) {
            return null;
        }

        $item->setIsEnabled(false);

        return $item;
    }
}
