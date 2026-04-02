<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Console\Command;

/** @mixin Command */
trait ResolvesUser
{
    private function resolveUser(): ?User
    {
        $identifier = (string) $this->argument('user');

        return is_numeric($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();
    }
}
