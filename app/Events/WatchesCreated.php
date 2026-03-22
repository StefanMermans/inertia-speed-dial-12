<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\Models\Watch;
use Illuminate\Foundation\Events\Dispatchable;

class WatchesCreated
{
    use Dispatchable;

    /**
     * @param  array<int, Watch>  $watches
     */
    public function __construct(
        public readonly array $watches,
        public readonly User $user,
    ) {}
}
