<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Data\Watches\SeriesProgressData;
use App\Enums\WatchProgressStatus;
use App\Models\User;
use App\Services\Watches\WatchProgressQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WatchProgressController
{
    public function __invoke(Request $request, WatchProgressQuery $query): Response
    {
        /** @var User $user */
        $user = $request->user();

        $byStatus = $query->forUser($user)->groupBy(
            fn (SeriesProgressData $progress): string => $progress->status->value,
        );

        return Inertia::render('watches/index', [
            'continueWatching' => $this->section($byStatus, WatchProgressStatus::InProgress),
            'needsMatch' => $this->section($byStatus, WatchProgressStatus::Unmatched),
            'completed' => $this->section($byStatus, WatchProgressStatus::Completed),
            'stale' => $this->section($byStatus, WatchProgressStatus::Stale),
            'movies' => Inertia::defer(fn (): Collection => $query->moviesForUser($user)),
        ]);
    }

    /**
     * @param  Collection<string, Collection<int, SeriesProgressData>>  $byStatus
     * @return Collection<int, SeriesProgressData>
     */
    private function section(Collection $byStatus, WatchProgressStatus $status): Collection
    {
        return $byStatus->get($status->value, collect())->values();
    }
}
