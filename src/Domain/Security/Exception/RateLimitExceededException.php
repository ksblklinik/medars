<?php

declare(strict_types=1);

namespace App\Domain\Security\Exception;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
}
