<?php
/**
 * Diglin GmbH - Switzerland.
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    OroAkeneoBundle
 * @copyright   2020 - Diglin (https://www.diglin.com)
 */

namespace Oro\Bundle\AkeneoBundle\Integration\Connector;

use Oro\Bundle\IntegrationBundle\Provider\AbstractConnector;

class AbstractOroAkeneoConnector extends AbstractConnector
{
    const LAST_SYNC_KEY = 'lastSyncItemDate';

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
}
