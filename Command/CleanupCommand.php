<?php

namespace Oro\Bundle\AkeneoBundle\Command;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clears old records from message_queue_job table before repack.
 */
class CleanupCommand extends Command implements CronCommandInterface
{
    private const INTERVAL_FOR_SUCCESSES = '-1 week';
    private const INTERVAL_FOR_FAILED = '-1 week';

    /** @var string */
    protected static $defaultName = 'oro:akeneo:message-queue:cleanup';

    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
        parent::__construct();
    }

    public function isActive()
    {
        return true;
    }

    public function getDefaultDefinition()
    {
        return '0 2 * * 6';
    }

    public function configure()
    {
        $this
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show the number of jobs that match the cleanup criteria instead of deletion.'
            )
            ->addOption(
                'interval-success',
                's',
                InputOption::VALUE_OPTIONAL,
                'Interval to remove successful jobs.',
                self::INTERVAL_FOR_SUCCESSES
            )
            ->addOption(
                'interval-fail',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Interval to remove failed jobs.',
                self::INTERVAL_FOR_FAILED
            )
            ->setDescription('Clears old records from message_queue_job table.')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command clears successful job records
                    that are older than <info>--interval-success</info> and failed job records older than <info>--interval-fail</info>
                    from <comment>message_queue_job</comment> table.

                      <info>php %command.full_name%</info>

                    The <info>--dry-run</info> option can be used to show the number of jobs that match
                    the cleanup criteria instead of deleting them:

                      <info>php %command.full_name% --dry-run</info>

                    HELP
            )
            ->addUsage('--dry-run --interval-success=now --interval-fail=\'-1 week\'')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            new \DateTime($input->getOption('interval-success'));
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf(
                    '<error>Invalid "%s" format: %s</error>',
                    'interval-success',
                    $input->getOption('interval-success')
                )
            );

            return 1;
        }

        try {
            new \DateTime($input->getOption('interval-fail'));
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf(
                    '<error>Invalid "%s" format: %s</error>',
                    'interval-fail',
                    $input->getOption('interval-fail')
                )
            );

            return 1;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(
                sprintf(
                    '<info>Number of jobs that would be deleted: %d</info>',
                    $this->countRecords($input->getOption('interval-success'), $input->getOption('interval-fail'))
                )
            );

            return;
        }

        $output->writeln(sprintf(
            '<comment>Number of jobs that has been deleted:</comment> %d',
            $this->deleteRecords($input->getOption('interval-success'), $input->getOption('interval-fail'))
        ));

        $output->writeln('<info>Message queue job history cleanup complete</info>');
    }

    private function deleteRecords(string $intervalSuccess, string $intervalFail)
    {
        $qb = $this->doctrineHelper
            ->getEntityManagerForClass(Job::class)
            ->getRepository(Job::class)
            ->createQueryBuilder('job');
        $qb->delete(Job::class, 'job');
        $this->addOutdatedJobsCriteria($qb, $intervalSuccess, $intervalFail);

        return $qb->getQuery()->execute();
    }

    private function countRecords(string $intervalSuccess, string $intervalFail)
    {
        $qb = $this->doctrineHelper
            ->getEntityManagerForClass(Job::class)
            ->getRepository(Job::class)
            ->createQueryBuilder('job');
        $qb->select('COUNT(job.id)');
        $this->addOutdatedJobsCriteria($qb, $intervalSuccess, $intervalFail);

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function addOutdatedJobsCriteria(QueryBuilder $qb, string $intervalSuccess, string $intervalFail): void
    {
        $jobName = 'oro_integration:sync_integration:%s%%';

        $integrations = $this->doctrineHelper
            ->createQueryBuilder(Channel::class, 'c')
            ->select('c.id')
            ->andWhere('c.type = :type')
            ->setParameter('type', AkeneoChannel::TYPE)
            ->getQuery()
            ->getScalarResult();
        $integrations = array_column($integrations, 'id');

        $expr = $qb->expr()->orX();
        foreach ($integrations as $integration) {
            $expr->add($qb->expr()->like('job.name', ':query'));
            $qb->setParameter('query', sprintf($jobName, $integration));
        }

        $qb
            ->andWhere($expr)
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->eq('job.status', ':status_success'),
                        $qb->expr()->lt('job.stoppedAt', ':success_end_time')
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->eq('job.status', ':status_failed'),
                        $qb->expr()->lt('job.stoppedAt', ':failed_end_time')
                    )
                )
            )
            ->setParameter('status_success', Job::STATUS_SUCCESS)
            ->setParameter(
                'success_end_time',
                new \DateTime($intervalSuccess, new \DateTimeZone('UTC')),
                Types::DATETIME_MUTABLE
            )
            ->setParameter('status_failed', Job::STATUS_FAILED)
            ->setParameter(
                'failed_end_time',
                new \DateTime($intervalFail, new \DateTimeZone('UTC')),
                Types::DATETIME_MUTABLE
            );
    }
}
