<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Processor;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface;

class AttributeFamilyStatusProcessor implements ProcessorInterface
{
    use CacheProviderAwareProcessor;

    /** @var array */
    private $processedAttributeFamilies = [];

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

        if (in_array($item->getCode(), $this->processedAttributeFamilies)) {
            return null;
        }

        $objectManager = $this->registry->getManagerForClass(AttributeFamily::class);
        $entityState = $objectManager
            ->getUnitOfWork()
            ->getEntityState($item);

        if ($entityState !== UnitOfWork::STATE_MANAGED) {
            $item = $objectManager->merge($item);
        }

        $item->setIsEnabled(false);

        return $item;
    }

    public function initialize()
    {
        $this->processedAttributeFamilies = $this->cacheProvider->fetch('attribute_family') ?? [];
    }

    public function flush()
    {
        $this->cacheProvider->delete('attribute_family');
        $this->processedAttributeFamilies = null;
    }
}
