<?php
declare(strict_types=1);

namespace Recruiter;

use Exception;

trait FinalizableBehaviour
{
    public function afterSuccess()
    {
    }

    public function afterFailure(Exception $e)
    {
    }

    public function afterLastFailure(Exception $e)
    {
    }

    public function finalize(?Exception $e = null)
    {
    }
}
