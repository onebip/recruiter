<?php
namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\Recruiter;

class FactoryMethodCommand implements Workable
{
    public static function from(/*$callable[, $argument, $argument...]*/)
    {
        $arguments = func_get_args();
        $callable = array_shift($arguments);
        list ($class, $method) = explode("::", $callable);

        return self::singleStep(self::stepFor($class, $method, $arguments));
    }

    private static function singleStep($step)
    {
        return new self([
            $step
        ]);
    }

    private static function stepFor($class, $method, $arguments)
    {
        $step = [
            'class' => $class,
            'method' => $method,
        ];
        if ($arguments) {
            $step['arguments'] = $arguments;
        }

        return $step;
    }

    private $steps;

    private function __construct(array $steps = [])
    {
        $this->steps = $steps;
    }

    public function asJobOf(Recruiter $recruiter)
    {
        return $recruiter->jobOf($this);
    }

    public function execute($retryOptions = null)
    {
        $result = null;
        foreach ($this->steps as $step) {
            if (isset($step['class'])) {
                $callable = $step['class'] . '::' . $step['method'];
            } else {
                $callable = [$result, $step['method']];
            }
            if (!is_callable($callable)) {
                $message = "The following step does not result in a callable: " . var_export($step, true) . ".";
                if (is_object($result)) {
                    $message .= ' Reached object: ' . get_class($result);
                } else {
                    $message .= ' Reached value: ' . var_export($result, true);
                }
                throw new \BadMethodCallException($message);
            }
            $arguments = $this->arguments($step);
            $result = call_user_func_array(
                $callable,
                $arguments
            );
        }
        return $result;
    }

    private function arguments($step)
    {
        $arguments = isset($step['arguments']) ? $step['arguments'] : [];

        return $arguments;
    }

    public function __call($method, $arguments)
    {
        $step = [
            'method' => $method,
        ];
        if ($arguments) {
            $step['arguments'] = $arguments;
        }
        $this->steps[] = $step;
        return $this;
    }

    public function export()
    {
        return [
            'steps' => $this->steps,
        ];
    }

    public static function import($document)
    {
        return new self($document['steps']);
    }
}
