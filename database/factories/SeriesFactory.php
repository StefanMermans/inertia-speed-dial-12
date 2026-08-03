<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TmdbMatchSource;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'year' => fake()->year(),
            'plex_rating_key' => (string) fake()->unique()->randomNumber(5),
        ];
    }

    public function withExternalIds(): static
    {
        return $this->state([
            'tmdb_id' => fake()->randomNumber(6),
            'imdb_id' => 'tt'.fake()->numerify('#######'),
            'tvdb_id' => fake()->randomNumber(6),
        ]);
    }

    /**
     * A series whose TMDB totals are known, so progress is calculable.
     */
    public function synced(int $totalEpisodes, ?int $tmdbId = null): static
    {
        return $this->state([
            'tmdb_id' => $tmdbId ?? fake()->unique()->randomNumber(6),
            'total_episodes' => $totalEpisodes,
            'total_seasons' => 1,
            'tmdb_status' => 'Ended',
            'tmdb_match_source' => TmdbMatchSource::Tvdb,
            'tmdb_synced_at' => now(),
            'poster_path' => '/'.fake()->lexify('??????????').'.jpg',
        ]);
    }

    /**
     * A Plex-created series that has never resolved to a TMDB entry.
     */
    public function unmatched(): static
    {
        return $this->state([
            'tmdb_id' => null,
            'total_episodes' => null,
            'total_seasons' => null,
            'tmdb_match_source' => null,
            'tmdb_synced_at' => null,
        ]);
    }

    /**
     * Matched only by title, which is the weak path worth flagging in the UI.
     */
    public function matchedByTitle(): static
    {
        return $this->state([
            'tmdb_match_source' => TmdbMatchSource::Title,
        ]);
    }

    public function stillAiring(): static
    {
        return $this->state([
            'tmdb_status' => Series::STATUS_RETURNING,
        ]);
    }
}
