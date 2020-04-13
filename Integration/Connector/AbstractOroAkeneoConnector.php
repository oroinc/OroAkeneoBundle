<?php
/**
 * Diglin GmbH - Switzerland.
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    OroAkeneoBundle
 * @copyright   2020 - Diglin (https://www.diglin.com)
 */

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Exception\RuntimeException;
use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;

abstract class AbstractOroAkeneoConnector extends AbstractConnector
{
    const LAST_SYNC_KEY = 'lastSyncItemDate';
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    public function read()
    {
        $item = parent::read();

        if (null !== $item) {
            $this->addStatusData(
                self::LAST_SYNC_KEY,
                $this->getMaxUpdatedDate($this->getUpdatedDate($item), $this->getStatusData(self::LAST_SYNC_KEY))
            );
        }

        return $item;
    }

    protected function getMaxUpdatedDate(?string $currDateToCompare = null, ?string $prevDateToCompare = null): ?string
    {
        if (!$prevDateToCompare) {
            $date = $currDateToCompare;
        } else {
            if (!$currDateToCompare) {
                $date = $prevDateToCompare;
            } else {
                $date = strtotime($currDateToCompare) > strtotime($prevDateToCompare)
                    ? $currDateToCompare
                    : $prevDateToCompare;
            }
        }

        return $date ? $this->getMinUpdatedDate($date) : $date;
    }

    /**
     * Compares maximum updated date with current date and returns the smallest.
     */
    protected function getMinUpdatedDate(string $updatedDate): string
    {
        $currentDate = new \DateTime('now', new \DateTimeZone('UTC'));
        if ($currentDate->getTimestamp() > strtotime($updatedDate)) {
            return $updatedDate;
        }

        return $currentDate->format('Y-m-d H:i:s');
    }

    /**
     * @param array $item
     *
     * @return string|null
     */
    protected function getUpdatedDate(array $item)
    {
        return $item['updated'] ?? null;
    }

    public function setManagerRegistry(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    protected function getLastCompletedIntegrationStatus(Integration $integration, $connector)
    {
        if (!$this->managerRegistry) {
            throw new RuntimeException('Doctrine manager registry is not initialized. Use setManagerRegistry method.');
        }

        return $this->managerRegistry->getRepository('OroIntegrationBundle:Channel')
            ->getLastStatusForConnector($integration, $connector, Status::STATUS_COMPLETED);
    }

    protected function getLastSyncDate(): ?\DateTime
    {
        $isForce = $this->getStepExecution()
            ->getJobExecution()
            ->getExecutionContext()
            ->get('force')
        ;

        return ($this->getStatusData(self::LAST_SYNC_KEY) && !$isForce)
            ? new \DateTime($this->getStatusData(self::LAST_SYNC_KEY), new \DateTimeZone('UTC'))
            : null;
    }
}
