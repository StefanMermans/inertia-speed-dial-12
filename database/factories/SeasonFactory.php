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
            'episode_count' => fake()->numberBetween(12, 25),
        ];
    }

    public function number(int $seasonNumber): static
    {
        return $this->state([
            'season_number' => $seasonNumber,
        ]);
    }

    public function withEpisodes(int $episodeCount): static
    {
        return $this->state([
            'episode_count' => $episodeCount,
        ]);
    }
}
