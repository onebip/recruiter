<?php
namespace Utils;

class Chainable
{
    /**
     * @var array
     */
    private $caughtExceptions;

    /**
     * @var mixed
     */
    private $returnValue;

    /**
     * @var \Exception
     */
    private $lastException = null;

    public function __construct(array $caughtExceptions) {
        $this->caughtExceptions = $caughtExceptions;
    }

    public static function avoidException($exceptionClass)
    {
        return new Self([$exceptionClass]);
    }

    public function do(callable $callable)
    {
        try {
            $this->returnValue = $callable($this->returnValue);
        } catch (\Exception $e) {
            $shouldBeCaught = array_reduce(
                $this->caughtExceptions,
                function ($shouldBeCaught, $exceptionClass) use ($e) {
                    return $shouldBeCaught || $e instanceof $exceptionClass;
                },
                false
            );

            if (!$shouldBeCaught) {
                throw $e;
            }

            $this->lastException = $e;
        }

        return $this;
    }

    public function andDo(callable $callable)
    {
        return $this->do($callable);
    }

    public function or(callable $callable)
    {
        if (is_null($this->lastException)) {
            return $this;
        }

        $this->returnValue = $this->lastException;
        $this->lastException = null;

        return $this->do($callable);
    }

    public function done()
    {
        if (!is_null($this->lastException)) {
            throw $this->lastException;
        }

        return $this->returnValue;
    }
}
