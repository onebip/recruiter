<?php
namespace Recruiter\Option;

use Recruiter;
use Recruiter\Factory;
use Ulrichsg\Getopt;
use UnexpectedValueException;
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
        $this->mongoFactory = new Factory();
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
            list($hosts, $dbName, $options) = $this->parse($target ?: $this->defaultTarget);
            return $this->mongoFactory->getMongoDb($hosts, $options, $dbName);
        } catch(MongoConnectionException $e) {
            throw new UnexpectedValueException(
                sprintf(
                    "Option '%s': no MongoDB running at '%s'",
                    $this->name, $hosts
                )
            );
        }
    }

    public static function parse($target)
    {
        if (preg_match(
                '/^'
                . '(mongodb:\/\/)?'
                . '(?P<hosts>[^\/]+)'
                . '(?:\/(?P<db>\w+))?'
                . '(\?(?P<qs>.*))?'
                . '/',
                $target,
                $matches
            )) {
            if (empty($matches['db'])) {
                $matches['db'] = 'recruiter';
            }
            if (empty($matches['qs'])) {
                $matches['qs'] = '';
            }
            parse_str($matches['qs'], $queryString);
            return [$matches['hosts'], $matches['db'], $queryString];
        }
        throw new UnexpectedValueException(
            sprintf(
                "Sorry, I don't recognize '%s' as valid MongoDB coordinates",
                $target
            )
        );
    }
}
