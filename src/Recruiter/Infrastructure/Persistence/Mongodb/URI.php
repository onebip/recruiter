<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Persistence\Mongodb;

use UnexpectedValueException;

/**
 * Class URI
 */
class URI
{
    const DEFAULT_URI = 'mongodb://127.0.0.1:27017/recruiter';

    /**
     * @var string
     */
    private $uri;

    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    public static function from(?string $uri): self
    {
        if (!$uri) {
            $uri = self::DEFAULT_URI;
        }

        return new self($uri);
    }

    public function database(): string
    {
        $parsed = parse_url($this->uri);
        if (!$parsed) {
            throw new \InvalidArgumentException("$this->uri is not a valid mongo uri");
        }

        return substr($parsed['path'], 1);
    }

    public function __toString()
    {
        return $this->uri;
    }
}
