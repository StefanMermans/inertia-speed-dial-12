<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'anilist_id' => null,
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
}
