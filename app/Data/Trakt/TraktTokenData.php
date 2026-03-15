<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktTokenData extends Data
{
    public function __construct(
        public readonly string $access_token,
        public readonly string $token_type,
        public readonly int $expires_in,
        public readonly string $refresh_token,
        public readonly string $scope,
        public readonly int $created_at,
    ) {}
}
