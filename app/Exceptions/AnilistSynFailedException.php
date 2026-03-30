<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class AnilistSynFailedException extends Exception
{
    public function __construct(int $anilistId, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Failed to sync media with id {$anilistId}", $code, $previous);
    }
}
