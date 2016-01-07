<?php
namespace Recruiter\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Recruiter\Workable\ShellCommand;
use Recruiter\Recruiter;

class RecruiterJobCommand extends Command
{
    private $recruiter;

    public function __construct(Recruiter $recruiter)
    {
        parent::__construct();
        $this->recruiter = $recruiter;
    }

    protected function configure()
    {
        $this
            ->setName('recruiter:command')
            ->setDescription('Runs a shell command inside the recruiter')
            ->addArgument(
                'shell_command',
                InputArgument::REQUIRED,
                'The command to run'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ShellCommand::fromCommandLine($input->getArgument('shell_command'))
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();
    }
}
