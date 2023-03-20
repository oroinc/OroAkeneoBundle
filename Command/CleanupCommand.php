<?php

namespace Oro\Bundle\AkeneoBundle\Command;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedIdentityQueryResultIterator;
use Oro\Bundle\CronBundle\Command\CronCommandActivationInterface;
use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\FieldsChanges;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clears old records from oro_integration_fields_changes table before repack.
 */
class CleanupCommand extends Command implements
    CronCommandScheduleDefinitionInterface,
    CronCommandActivationInterface
{
    /** @var string */
    protected static $defaultName = 'oro:cron:akeneo:cleanup';

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
            ->setDescription('Clears old records from oro_integration_fields_changes table.')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command clears fields changes for complete job records
                    from <comment>oro_integration_fields_changes</comment> table.

                      <info>php %command.full_name%</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf(
            '<comment>Number of fields changes that has been deleted:</comment> %d',
            $this->deleteRecords()
        ));

        $output->writeln('<info>Fields changes cleanup complete</info>');

        return 0;
    }

    private function deleteRecords(): int
    {
        $qb = $this->doctrineHelper
            ->getEntityManagerForClass(FieldsChanges::class)
            ->getRepository(FieldsChanges::class)
            ->createQueryBuilder('fc');

        $qb
            ->delete(FieldsChanges::class, 'fc')
            ->where($qb->expr()->eq('fc.entityClass', ':class'))
            ->setParameter('class', Job::class)
            ->andWhere($qb->expr()->in('fc.entityId', ':ids'));

        $jqb = $this->doctrineHelper
            ->getEntityManagerForClass(Job::class)
            ->getRepository(Job::class)
            ->createQueryBuilder('j');

        $jqb
            ->select('j.id')
            ->where($jqb->expr()->in('j.status', ':statuses'))
            ->setParameter('statuses', [Job::STATUS_SUCCESS, Job::STATUS_CANCELLED, Job::STATUS_FAILED, Job::STATUS_STALE])
            ->orderBy($jqb->expr()->desc('j.id'));

        $iterator = new BufferedIdentityQueryResultIterator($jqb->getQuery());

        $result = 0;
        $iterator->setPageLoadedCallback(function (array $rows) use ($qb, &$result): array {
            $ids = array_column($rows, 'id');

            $result = $result + $qb->setParameter('ids', $ids)->getQuery()->execute();

            return $ids;
        });

        iterator_to_array($iterator);

        return $result;
    }
}
