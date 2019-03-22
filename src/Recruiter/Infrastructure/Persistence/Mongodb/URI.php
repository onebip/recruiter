<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Persistence\Mongodb;

use MongoConnectionException;
use UnexpectedValueException;

/**
 * Class URI
 */
class URI
{
    const DEFAULT_DB_NAME = 'recruiter';

    /**
     * @var string
     */
    private $host;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     */
    private $dbName;

    /**
     * @param string $host
     * @param array $options
     * @param string $dbName
     */
    public function __construct(string $host, string $dbName, array $options = [])
    {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->options = $options;
    }

    public static function from(string $uri): self
    {
        $mongoUriFormat =
            '/^'
            . '(mongodb:\/\/)?'
            . '(?P<hosts>[^\/]+)'
            . '(?:\/(?P<db>\w+))?'
            . '(\?(?P<qs>.*))?'
            . '/';

        if (preg_match($mongoUriFormat, $uri, $matches)) {
            if (empty($matches['db'])) {
                $matches['db'] = self::DEFAULT_DB_NAME;
            }

            if (empty($matches['qs'])) {
                $matches['qs'] = '';
            }

            $options = self::optionsFrom($matches['qs']);

            return new self($matches['hosts'], $matches['db'], $options);
        }

        throw new UnexpectedValueException(
            sprintf(
                "Sorry, I don't recognize '%s' as valid MongoDB coordinates",
                $uri
            )
        );
    }

    private static function optionsFrom(string $queryString): array
    {
        parse_str($queryString, $options);
        foreach ($options as $key => $value) {
            if (preg_match('/^\d+$/', $value)) {
                $options[$key] = intval($value);
            }
            if (empty($options[$key])) {
                unset($options[$key]);
            }
        }

        return $options;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function dbName(): string
    {
        return $this->dbName;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function __toString()
    {
        return "$this->host/$this->dbName";
    }
}
