<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\User;

/** @mixin \Illuminate\Console\Command */
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
