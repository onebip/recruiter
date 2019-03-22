<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Filesystem;

use Recruiter\Recruiter;
use UnexpectedValueException;

/**
 * Class BootstrapFile
 */
class BootstrapFile
{
    /**
     * @var string
     */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $this->validate($filePath);
    }

    public static function fromFilePath(string $filePath): Self
    {
        return new Static($filePath);
    }

    public function load(Recruiter $recruiter)
    {
        return require $this->filePath;
    }

    private function validate($filePath): string
    {
        if (!file_exists($filePath)) {
            $this->throwBecauseFile($filePath, "doesn't exists");
        }

        if (!is_readable($filePath)) {
            $this->throwBecauseFile($filePath, "is not readable");
        }

        return $filePath;
    }

    private function throwBecauseFile($filePath, $reason)
    {
        throw new UnexpectedValueException(
            sprintf(
                "Bootstrap file has an invalid value: file '%s' %s",
                $filePath,
                $reason
            )
        );
    }
}
