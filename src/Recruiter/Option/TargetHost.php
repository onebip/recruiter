<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use UnexpectedValueException;

use MongoClient;
use MongoConnectionException;

class TargetHost implements Recruiter\Option
{
    private $name;
    private $defaultTarget;
    private $defaultHost;
    private $defaultPort;
    private $defaultDb;

    public function __construct($name)
    {
        $this->name = $name;
        $this->defaultHost = 'localhost';
        $this->defaultPort = '27017';
        $this->defaultDb = 'recruiter';
        $this->defaultTarget =
            $this->defaultHost . ':' .
            $this->defaultPort . '/' .
            $this->defaultDb;
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf('HOSTNAME[:PORT][/DB] MongoDB coordinates [%s]', $this->defaultTarget)
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine) {
        $recruiter = new Recruiter\Recruiter(
            $this->validate(
                $optionsFromCommandLine->getOption($this->name)
            )
        );
        $recruiter->createCollectionsAndIndexes();
        return $recruiter;
    }

    private function validate($target)
    {
        try {
            list($host, $port, $db) = $this->parse($target ?: $this->defaultTarget);
            return (new MongoClient($host . ':' . $port))->selectDB($db);
        } catch(MongoConnectionException $e) {
            throw new UnexpectedValueException(
                sprintf(
                    "Option '%s': no MongoDB running at '%s:%s'",
                    $this->name, $host, $port
                )
            );
        }
    }

    private function parse($target)
    {
        if (preg_match('/^(?P<host>[^:\/]+)(?::(?P<port>\d+))?(?:\/(?P<db>\w+))?/', $target, $matches)) {
            if (empty($matches['port'])) {
                $matches['port'] = '27017';
            }
            if (empty($matches['db'])) {
                $matches['db'] = 'recruiter';
            }
            return [$matches['host'], $matches['port'], $matches['db']];
        }
        throw new UnexpectedValueException(
            sprintf(
                "Option '%s': Sorry, I don't recognize '%s' as valid MongoDB coordinates",
                $this->name, $target
            )
        );
    }
}

