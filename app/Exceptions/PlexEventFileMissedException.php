<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class PlexEventFileMissedException extends Exception
{
    public function __construct(
        public string $filepath
    ) {
        parent::__construct("Missed a plex event file, saved it at: {$filepath}");
    }
}
