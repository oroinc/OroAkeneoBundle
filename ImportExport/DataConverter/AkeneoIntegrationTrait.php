<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\Localization;

/**
 * @property ContextInterface $context
 */
trait AkeneoIntegrationTrait
{
    /** @var AkeneoSettings */
    protected $transport;

    /**
     * @var ContextInterface
     */
    protected $context;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    public function setRegistry(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function setImportExportContext(ContextInterface $context)
    {
        $this->context = $context;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    /**
     * @return Localization
     */
    protected function getDefaultLocalization()
    {
        return $this->registry
            ->getRepository(Localization::class)
            ->find($this->configManager->get('oro_locale.default_localization'));
    }

    /**
     * @return AkeneoSettings
     */
    private function getTransport()
    {
        if ($this->transport) {
            return $this->transport;
        }

        if (!$this->context || false === $this->context->hasOption('channel')) {
            return null;
        }

        $channelId = $this->context->getOption('channel');
        $channel = $this->registry->getRepository(Channel::class)->find($channelId);

        if (!$channel) {
            return null;
        }

        return $channel->getTransport();
    }

    /**
     * @return Localization[]
     */
    private function getLocalizations(string $code)
    {
        return $this->registry
            ->getRepository(Localization::class)
            ->createQueryBuilder('l')
            ->select('l')
            ->leftJoin('l.language', 'la')
            ->where('la.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult();
    }
}
