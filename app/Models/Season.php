<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    protected $fillable = [
        'series_id',
        'season_number',
        'anilist_id',
        'mal_id',
        'episode_count',
        'format',
    ];

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return HasMany<Watch, $this> */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }
}
