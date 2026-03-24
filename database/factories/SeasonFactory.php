<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Season;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'series_id' => Series::factory(),
            'season_number' => 1,
            'anilist_id' => fake()->unique()->randomNumber(5),
            'mal_id' => fake()->randomNumber(5),
            'episode_count' => fake()->numberBetween(12, 25),
            'format' => 'TV',
        ];
    }
}
