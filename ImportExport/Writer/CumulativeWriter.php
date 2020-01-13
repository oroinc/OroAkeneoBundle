<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Writer;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\ItemWriterInterface;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\BatchBundle\Item\Support\ClosableInterface;
use Oro\Bundle\BatchBundle\Step\StepExecutionRestoreInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\PlatformBundle\Manager\OptionalListenerManager;

class CumulativeWriter implements
    ItemWriterInterface,
    ClosableInterface,
    StepExecutionAwareInterface,
    StepExecutionRestoreInterface
{
    const MAX_UOW_OBJECTS_WITH_CHANGES = 150;
    const MAX_UOW_OBJECTS_WITHOUT_CHANGES = 200;
    const MAX_UOW_OPERATIONS = 100;

    protected static $usage = [];

    /** @var int */
    protected $batchSize = self::MAX_UOW_OPERATIONS;

    /** @var ItemWriterInterface */
    private $writer;

    /** @var OptionalListenerManager */
    private $optionalListenerManager;

    /** @var ManagerRegistry */
    private $registry;

    /** @var ConfigManager */
    private $configManager;

    /** @var array */
    private $items = [];

    private $read = 0;

    public function __construct(
        ItemWriterInterface $writer,
        OptionalListenerManager $optionalListenerManager,
        ManagerRegistry $registry,
        ConfigManager $configManager
    ) {
        $this->writer = $writer;
        $this->optionalListenerManager = $optionalListenerManager;
        $this->registry = $registry;
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $entityManager = $this->registry->getManager();

        $class = null;

        $this->read += count($items);

        $item = reset($items);
        if ($item) {
            $class = ClassUtils::getClass($item);

            $itemEntityManager = $this->registry->getManagerForClass($class);
            if ($itemEntityManager !== $entityManager) {
                $this->doWrite($items);

                return;
            }
        }

        foreach ($items as $item) {
            $this->items[] = $item;

            $entityManager->persist($item);
        }

        if ($this->skipFlush($items)) {
            return;
        }

        $this->doWrite($this->items);
    }

    private function doWrite(array &$items)
    {
        try {
            $this->optionalListenerManager->disableListeners(
                $this->optionalListenerManager->getListeners()
            );
            $this->optionalListenerManager
                ->enableListener('oro_entity.event_listener.entity_modify_created_updated_properties_listener');

            $this->writer->write($items);
        } finally {
            unset($items);
            $this->items = [];

            $entityManager = $this->registry->getManager();
            $entityManager->flush();
            $entityManager->clear();

            $this->optionalListenerManager->enableListeners(
                $this->optionalListenerManager->getListeners()
            );
        }
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function skipFlush(array &$items): bool
    {
        $letsWrite = false;
        $letsSkip = true;

        /** @var EntityManager $entityManager */
        $entityManager = $this->registry->getManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $count = $this->getUnitOfWorkChangesCount($unitOfWork);
        if ($count > $this->batchSize) {
            return $letsWrite;
        }

        $entityStates = $this->getUnitOfWorkStatesCount($unitOfWork);
        if ($entityStates > self::MAX_UOW_OBJECTS_WITHOUT_CHANGES) {
            return $letsWrite;
        }

        if ($count && $entityStates > self::MAX_UOW_OBJECTS_WITH_CHANGES) {
            return $letsWrite;
        }

        if (!$count) {
            return $letsSkip;
        }

        foreach ($items as $item) {
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata(ClassUtils::getClass($item)), $item);
        }

        $count = $this->getUnitOfWorkChangesCount($unitOfWork);
        if ($count > $this->batchSize) {
            return $letsWrite;
        }

        $entityStates = $this->getUnitOfWorkStatesCount($unitOfWork);
        if ($entityStates > self::MAX_UOW_OBJECTS_WITHOUT_CHANGES) {
            return $letsWrite;
        }

        if ($count && $entityStates > self::MAX_UOW_OBJECTS_WITH_CHANGES) {
            return $letsWrite;
        }

        return $letsSkip;
    }

    private function getUnitOfWorkChangesCount(UnitOfWork $unitOfWork): int
    {
        return
            count($unitOfWork->getScheduledEntityInsertions()) +
            count($unitOfWork->getScheduledEntityUpdates()) +
            count($unitOfWork->getScheduledEntityDeletions()) +
            count($unitOfWork->getScheduledCollectionUpdates()) +
            count($unitOfWork->getScheduledCollectionDeletions());
    }

    private function getUnitOfWorkStatesCount(UnitOfWork $unitOfWork): int
    {
        $entityStates = \Closure::bind(
            function ($property) {
                return $this->{$property};
            },
            $unitOfWork,
            $unitOfWork
        )(
            'entityStates'
        );

        return count($entityStates);
    }

    public function setOptionalListenerManager(OptionalListenerManager $optionalListenerManager)
    {
        $this->optionalListenerManager = $optionalListenerManager;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        if ($this->writer instanceof StepExecutionAwareInterface) {
            $this->writer->setStepExecution($stepExecution);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreStepExecution()
    {
        if ($this->writer instanceof StepExecutionRestoreInterface) {
            $this->writer->restoreStepExecution();
        }
    }

    /** {@inheritdoc} */
    public function close()
    {
        $this->doWrite($this->items);

        if ($this->writer instanceof ClosableInterface) {
            $this->writer->close();
        }
    }

    public function initialize()
    {
        if (method_exists($this->writer, 'initialize')) {
            $this->writer->initialize();
        }
    }

    public function flush()
    {
        if (method_exists($this->writer, 'flush')) {
            $this->writer->flush();
        }
    }

    public function setBatchSize(int $batchSize)
    {
        $this->batchSize = $batchSize;
    }
}
