<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Command;

use ByteUnits;
use Exception;
use Geezer\Command\RobustCommand;
use Geezer\Command\RobustCommandRunner;
use Geezer\Leadership\Dictatorship;
use Geezer\Leadership\LeadershipStrategy;
use Geezer\Timing\ExponentialBackoffStrategy;
use Geezer\Timing\WaitStrategy;
use Onebip\Concurrency\MongoLock;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Recruiter\Cleaner;
use Recruiter\Factory;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Job\Repository;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Timeless\Interval;

class CleanerCommand implements RobustCommand
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var Cleaner
     */
    private $cleaner;

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
     * @var Interval
     */
    private $gracePeriod;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param mixed $factory
     */
    public function __construct($factory, LoggerInterface $logger)
    {
        $this->factory = $factory;
        $this->logger = $logger;
    }

    public static function toRobustCommand(Factory $factory, LoggerInterface $logger): RobustCommandRunner
    {
        return new RobustCommandRunner(new static($factory, $logger), $logger);
    }

    public function execute(): bool
    {
        /* $this->log('cready to clean!'); */
        $numberOfJobsCleaned = $this->cleaner->cleanArchived($this->gracePeriod);
        $memoryUsage = ByteUnits\bytes(memory_get_usage());

        $this->log(sprintf(
            '[%s] cleaned up %d old jobs from the archive' . PHP_EOL,
            $memoryUsage->format(),
            $numberOfJobsCleaned
        ));

        $this->log(sprintf('going to sleep for %sms', $this->waitStrategy->current()));

        $this->memoryLimit->ensure($memoryUsage);

        return $numberOfJobsCleaned > 0;
    }

    public function shutdown(?Exception $e = null): bool
    {
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
        return 'start:cleaner';
    }

    public function description(): string
    {
        return 'process that clean archived jobs';
    }

    public function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('target', 't', InputOption::VALUE_REQUIRED, 'HOSTNAME[:PORT][/DB] MongoDB coordinates', 'mongodb://localhost:27017/recruiter'),
            new InputOption('clean-after', 'c', InputOption::VALUE_REQUIRED, 'delete jobs after :period', '5days'),
            new InputOption('wait-at-least', null, InputOption::VALUE_REQUIRED, 'Time to wait at least before to search for jobs to clear', '1m'),
            new InputOption('wait-at-most', null, InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before next polling', '3m'),
            new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Maximum amount of memory allocable', '256MB'),
            new InputOption('lease-time', 'l', InputOption::VALUE_REQUIRED, 'Maximum time to hold a lock before a refresh (seconds)', 60),
        ]);
    }

    public function init(InputInterface $input): void
    {
        /** @var string */
        $mongoTarget = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($mongoTarget));

        $this->waitStrategy = new ExponentialBackoffStrategy(
            Interval::parse($input->getOption('wait-at-least'))->ms(),
            Interval::parse($input->getOption('wait-at-most'))->ms()
        );
        $this->memoryLimit = new MemoryLimit($input->getOption('memory-limit'));
        $this->gracePeriod = Interval::parse($input->getOption('clean-after'));

        $lock = MongoLock::forProgram('CLEANER', $db->selectCollection('metadata'));
        $this->leadershipStrategy = new Dictatorship($lock, intval($input->getOption('lease-time')));

        $jobRepository = new Repository($db);
        $this->cleaner = new Cleaner($jobRepository);
    }

    private function log(string $message, string $level = LogLevel::DEBUG): void
    {
        $this->logger->log(
            $level,
            $message,
            [
                'hostname' => gethostname(),
                'program' => $this->name(),
                'datetime' => date('c'),
                'pid' => posix_getpid(),
            ]
        );
    }
}
