<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbRequestTokenData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly int $status_code,
        public readonly string $status_message,
        public readonly string $request_token,
    ) {}
}
