<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use GracefulDeath;

class Supervisor implements Recruiter\Option
{
    private $name;
    private $shouldRespawn;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::NO_ARGUMENT))
            ->setDescription('Respawn process if it crash');
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine) {
        $this->shouldRespawn = (bool) $optionsFromCommandLine->getOption($this->name);
        return $this;
    }

    public function applyTo(GracefulDeath\Builder $builder)
    {
        if ($this->shouldRespawn) {
            return $builder
                ->reanimationPolicy(function() {
                    printf(
                        '[SUPERVISOR][%d][%s] process died, respawn...' . PHP_EOL,
                        posix_getpid(), date('c')
                    );
                    return true;
                })
                ->avoidFutileMedicalCare();
        }
        return $builder
            ->reanimationPolicy(function() {
                printf(
                    '[SUPERVISOR][%d][%s] %s' . PHP_EOL, posix_getpid(), date('c'),
                    "process died, I will let it go, start with --{$this->name} options if you wish otherwise"
                );
                return false;
            });
    }
}
