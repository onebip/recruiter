<?php
declare(strict_types=1);

namespace Recruiter\Command;

use ByteUnits;
use DateTimeImmutable;
use Geezer\Command\RobustCommand;
use Geezer\Leadership\Dictatorship;
use Geezer\Leadership\LeadershipStrategy;
use Geezer\Timing\ExponentialBackoffStrategy;
use Geezer\Timing\WaitStrategy;
use Onebip\Clock\SystemClock;
use Onebip\Concurrency\MongoLock;
use Recruiter\Factory;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Persistence\Mongodb\URI;
use Recruiter\Recruiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timeless\Interval;

class RecruiterCommand implements RobustCommand
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var Recruiter
     */
    private $recruiter;

    /**
     * @var Interval
     */
    private $consideredDeadAfter;

    /**
     * @var LeadershipStrategy
     */
    private $leadershipStrategy;

    /**
     * @var WaitStrategy
     */
    private $waitStrategy;

    /**
     * @var MemoryLimit
     */
    private $memoryLimit;

    /**
     * @param mixed $factory
     */
    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    public function execute(): bool
    {
        $this->rollbackLockedJobs();
        $assignment = $this->assignJobsToWorkers();
        $this->retireDeadWorkers();

        $this->log(sprintf('going to sleep for %sms', $this->waitStrategy->current()));

        return count($assignment) > 0;
    }

    private function rollbackLockedJobs()
    {
        $rollbackStartAt = microtime(true);
        $rolledBack = $this->recruiter->rollbackLockedJobs();
        $rollbackEndAt = microtime(true);
        $this->log(sprintf('rolled back %d jobs in %fms', $rolledBack, ($rollbackEndAt - $rollbackStartAt) * 1000));
    }

    private function assignJobsToWorkers(): array
    {
        $pickStartAt = microtime(true);
        list ($assignment, $actualNumber) = $this->recruiter->assignJobsToWorkers();
        $pickEndAt = microtime(true);
        foreach ($assignment as $worker => $job) {
            $this->log(sprintf(' tried to assign job `%s` to worker `%s`', $job, $worker));
        }
        $memoryUsage = ByteUnits\bytes(memory_get_usage());

        $this->log(sprintf(
            '[%s] picked jobs for %d workers in %fms, actual assignments were %d',
            $memoryUsage->format(),
            count($assignment),
            ($pickEndAt - $pickStartAt) * 1000,
            $actualNumber
        ));

        $this->memoryLimit->ensure($memoryUsage);

        return $assignment;
    }

    private function retireDeadWorkers()
    {
        $unlockedJobs = $this->recruiter->retireDeadWorkers(
            new SystemClock(),
            $this->consideredDeadAfter
        );
        $this->log(sprintf('unlocked %d jobs due to dead workers', $unlockedJobs));
    }

    public function shutdown(): bool
    {
        $this->recruiter->bye();
        $this->log('ok, see you space cowboy...');

        return true;
    }

    public function leadershipStrategy(): LeadershipStrategy
    {
        return $this->leadershipStrategy;
    }

    public function waitStrategy(): WaitStrategy
    {
        return $this->waitStrategy;
    }

    public function name(): string
    {
        return 'recruiter:recruiter';
    }

    public function description(): string
    {
        return 'process that look up for new jobs to assign to workers';
    }

    public function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('target', 't', InputOption::VALUE_REQUIRED, 'HOSTNAME[:PORT][/DB] MongoDB coordinates', 'mongodb://localhost:27017/recruiter'),
            new InputOption('backoff-to', 'b', InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before next polling (milliseconds)', '1600'),
            new InputOption('backoff-from', null, InputOption::VALUE_REQUIRED, 'Time to wait at least before to search for new jobs (milliseconds)', '200'),
            new InputOption('lease-time', 'l', InputOption::VALUE_REQUIRED, 'Maximum time to hold a lock before a refresh (seconds)', 60),
            new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Maximum amount of memory allocable', '256MB'),
            new InputOption('considered-dead-after', 'd', InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before considering a worker dead', '30m'),
        ]);
    }

    public function init(InputInterface $input): void
    {
        $db = $this->factory->getMongoDb2(URI::from($input->getOption('target')));
        $lock = MongoLock::forProgram('RECRUITER', $db->selectCollection('metadata'));

        $this->leadershipStrategy = new Dictatorship($lock, intval($input->getOption('lease-time')));

        $this->waitStrategy = new ExponentialBackoffStrategy(intval($input->getOption('backoff-from')), intval($input->getOption('backoff-to')));

        $this->consideredDeadAfter = Interval::parse($input->getOption('considered-dead-after'));

        $this->memoryLimit = new MemoryLimit($input->getOption('memory-limit'));

        $this->recruiter = new Recruiter($db);
        $this->recruiter->createCollectionsAndIndexes();
    }

    private function log(string $message): void
    {
        printf(
            '[RECRUITER][%d][%s] %s' . PHP_EOL,
            posix_getpid(),
            date('c'),
            $message
        );
    }
}
