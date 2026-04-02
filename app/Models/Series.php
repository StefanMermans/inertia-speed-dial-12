<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'year', 'plex_rating_key', 'tmdb_id', 'imdb_id', 'tvdb_id', 'poster_path', 'anilist_id', 'mal_id'])]
final class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

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
}
