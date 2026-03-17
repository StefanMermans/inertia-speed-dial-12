<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WatchType;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Watch>
 */
class WatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => WatchType::Movie,
            'title' => fake()->words(3, true),
            'year' => fake()->year(),
            'watched_at' => fake()->dateTimeBetween('-1 year'),
            'plex_rating_key' => (string) fake()->unique()->randomNumber(5),
        ];
    }

    public function forMovie(): static
    {
        return $this->state([
            'type' => WatchType::Movie,
        ]);
    }

    public function forEpisode(): static
    {
        return $this->state(fn () => [
            'type' => WatchType::Episode,
            'series_id' => Series::factory(),
            'season_number' => fake()->numberBetween(1, 5),
            'episode_number' => fake()->numberBetween(1, 24),
        ]);
    }
}
