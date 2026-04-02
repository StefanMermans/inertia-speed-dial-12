<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['series_id', 'season_number', 'anilist_id', 'mal_id', 'episode_count', 'format'])]
final class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'season_number' => 'integer',
            'anilist_id' => 'integer',
        ];
    }

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
