<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'year',
        'plex_rating_key',
        'tmdb_id',
        'imdb_id',
        'tvdb_id',
        'poster_path',
        'anilist_id',
        'mal_id',
    ];

    /** @return HasMany<Watch, $this> */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }
}
