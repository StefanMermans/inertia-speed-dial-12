<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TmdbMatchSource;
use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'year',
    'plex_rating_key',
    'tmdb_id',
    'imdb_id',
    'tvdb_id',
    'poster_path',
    'total_episodes',
    'total_seasons',
    'tmdb_status',
    'tmdb_match_source',
    'tmdb_synced_at',
    'tmdb_match_attempted_at',
    'merged_into_id',
])]
final class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * TMDB keeps adding episodes to these, so their totals go stale and need re-syncing.
     *
     * Kept as a plain string rather than an enum because TMDB may return statuses we do
     * not know about, and an enum cast would fail to hydrate on an unexpected value.
     */
    public const string STATUS_RETURNING = 'Returning Series';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_episodes' => 'integer',
            'total_seasons' => 'integer',
            'tmdb_match_source' => TmdbMatchSource::class,
            'tmdb_synced_at' => 'datetime',
            'tmdb_match_attempted_at' => 'datetime',
        ];
    }

    /**
     * Where this series went when it was merged away, if it was.
     *
     * @return BelongsTo<Series, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Follows the merge chain to the row that is actually in use.
     */
    public function current(): self
    {
        $series = $this;

        while ($series->merged_into_id !== null) {
            $next = self::withTrashed()->find($series->merged_into_id);

            if (! $next instanceof self || $next->is($series)) {
                break;
            }

            $series = $next;
        }

        return $series;
    }

    /** @return HasMany<Season, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /** @return HasMany<Watch, $this> */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }

    public function isMatched(): bool
    {
        return $this->tmdb_id !== null;
    }

    /**
     * Series worth handing to a TMDB sync.
     *
     * Two separate reasons: it has never synced successfully, or it is still airing and
     * its totals have aged. The first is rate-limited by `tmdb_match_attempted_at` so a
     * show TMDB cannot currently identify is retried periodically rather than either
     * being hammered every hour or — as it was — parked forever after one failure.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function needsTmdbSync(Builder $query, int $staleAfterDays, int $retryMatchAfterDays): void
    {
        $query->where(function (Builder $query) use ($staleAfterDays, $retryMatchAfterDays): void {
            $query->where(function (Builder $query) use ($retryMatchAfterDays): void {
                $query->whereNull('tmdb_synced_at')
                    ->where(function (Builder $query) use ($retryMatchAfterDays): void {
                        $query->whereNull('tmdb_match_attempted_at')
                            ->orWhere('tmdb_match_attempted_at', '<', now()->subDays($retryMatchAfterDays));
                    });
            })->orWhere(function (Builder $query) use ($staleAfterDays): void {
                $query->where('tmdb_status', self::STATUS_RETURNING)
                    ->where('tmdb_synced_at', '<', now()->subDays($staleAfterDays));
            });
        });
    }
}
