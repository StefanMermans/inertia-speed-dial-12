<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbAccessTokenData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly int $status_code,
        public readonly string $status_message,
        public readonly string $account_id,
        public readonly string $access_token,
    ) {}
}
