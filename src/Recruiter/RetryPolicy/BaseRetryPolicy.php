<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;

abstract class BaseRetryPolicy implements RetryPolicy
{
    use RetryPolicyBehaviour;
}
