<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Series;
use App\Models\User;

class SeriesPolicy
{
    /**
     * Series rows are shared rather than user-scoped, so a user may only re-point one
     * they have actually watched.
     */
    public function update(User $user, Series $series): bool
    {
        return $series->watches()->where('user_id', $user->id)->exists();
    }
}
