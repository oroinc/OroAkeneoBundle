<?php

namespace Oro\Bundle\AkeneoBundle\Command;

use Doctrine\Common\Cache\CacheProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clear Akeneo related caches
 */
class CacheClearCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'oro:akeneo:cache:clear';

    /** @var CacheProvider */
    private $cache;

    public function __construct(CacheProvider $cache)
    {
        $this->cache = $cache;
        parent::__construct();
    }

    public function isActive()
    {
        return true;
    }

    public function configure()
    {
        $this
            ->setDescription('Clear Akeneo related caches.')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command clears Akeneo related caches.

                    <info>php %command.full_name%</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cache->deleteAll();

        return 0;
    }
}
