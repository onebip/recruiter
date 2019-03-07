<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Command;

use ByteUnits;
use DateTimeImmutable;
use Geezer\Command\RobustCommand;
use Geezer\Leadership\Anarchy;
use Geezer\Leadership\Dictatorship;
use Geezer\Leadership\LeadershipStrategy;
use Geezer\Timing\ExponentialBackoffStrategy;
use Geezer\Timing\WaitStrategy;
use Onebip\Clock\SystemClock;
use Onebip\Concurrency\MongoLock;
use Recruiter\Factory;
use Recruiter\Infrastructure\Filesystem\BootstrapFile;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Recruiter\Worker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timeless\Interval;

class WorkerCommand implements RobustCommand
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var LeadershipStrategy
     */
    private $leadershipStrategy;

    /**
     * @var WaitStrategy
     */
    private $waitStrategy;

    /**
     * @param mixed $factory
     */
    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    public function execute(): bool
    {
        /* $this->log(sprintf('worker `%s` ready to work!', $this->worker->id())); */

        $doneSomeWork = $this->worker->work();

        if ($doneSomeWork) {
            $this->log(sprintf('executed job `%s`', $doneSomeWork));
        }

        $this->log(sprintf('going to sleep for %sms', $this->waitStrategy->current()));

        return (bool) $doneSomeWork;
    }

    public function shutdown(): bool
    {
        if ($this->worker->retireIfNotAssigned()) {
            $this->log(sprintf('worker `%s` retired', $this->worker->id()));
            $this->log(sprintf('ok, see you space cowboy...'));

            return true;
        }

        return false;
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
        return 'start:worker';
    }

    public function description(): string
    {
        return 'process that completes a previously assigned job';
    }

    public function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('target', 't', InputOption::VALUE_REQUIRED, 'HOSTNAME[:PORT][/DB] MongoDB coordinates', 'mongodb://localhost:27017/recruiter'),
            new InputOption('backoff-to', 'b', InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before next polling', '6400ms'),
            new InputOption('backoff-from', null, InputOption::VALUE_REQUIRED, 'Time to wait at least before to search for new jobs', '200ms'),
            new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Maximum amount of memory allocable', '64MB'),
            new InputOption('work-on', 'g', InputOption::VALUE_REQUIRED, 'Work only on jobs grouped with this label [%s]'),
            new InputOption('bootstrap', 's', InputOption::VALUE_REQUIRED, 'A PHP file that loads the worker environment'),
        ]);
    }

    public function init(InputInterface $input): void
    {
        $db = $this->factory->getMongoDb(MongoURI::from($input->getOption('target')));

        $this->waitStrategy = new ExponentialBackoffStrategy(
            Interval::parse($input->getOption('backoff-from'))->ms(),
            Interval::parse($input->getOption('backoff-to'))->ms()
        );

        $memoryLimit = new MemoryLimit($input->getOption('memory-limit'));

        $this->leadershipStrategy = new Anarchy();

        $recruiter = new Recruiter($db);

        if ($input->getOption('bootstrap')) {
            BootstrapFile::fromFilePath($input->getOption('bootstrap'))->load($recruiter);
        }

        $this->worker = $recruiter->hire($memoryLimit);
        if ($input->getOption('work-on')) {
            $this->worker->workOnJobsGroupedAs($input->getOption('work-on'));
        }
    }

    private function log(string $message): void
    {
        printf(
            '[WORKER][%d][%s] %s' . PHP_EOL,
            posix_getpid(),
            date('c'),
            $message
        );
    }
}
