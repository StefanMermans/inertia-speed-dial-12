<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WatchType;
use Database\Factories\WatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Watch extends Model
{
    /** @use HasFactory<WatchFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'year',
        'tmdb_id',
        'imdb_id',
        'tvdb_id',
        'series_id',
        'season_id',
        'season_number',
        'episode_number',
        'sort_order',
        'watched_at',
        'plex_rating_key',
        'anilist_id',
        'mal_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => WatchType::class,
            'watched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
