<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\Entity\Localization;

/**
 * @property DoctrineHelper $doctrineHelper
 * @property ConfigManager $configManager
 */
trait LocalizationAwareTrait
{
    /**
     * @return Localization
     */
    protected function getDefaultLocalization()
    {
        return $this->doctrineHelper
            ->getEntityReference(
                Localization::class,
                $this->configManager->get('oro_locale.default_localization')
            );
    }

    /**
     * @return Localization[]
     */
    private function getLocalizations(string $code)
    {
        return $this->doctrineHelper
            ->getEntityRepository(Localization::class)
            ->createQueryBuilder('l')
            ->select('l')
            ->leftJoin('l.language', 'la')
            ->where('la.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult();
    }
}
