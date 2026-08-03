<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WatchType;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use DateTimeInterface;
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
        ];
    }

    public function withPlexRatingKey(): static
    {
        return $this->state([
            'plex_rating_key' => (string) fake()->unique()->randomNumber(5),
        ]);
    }

    public function movie(): static
    {
        return $this->state([
            'type' => WatchType::Movie,
        ]);
    }

    /**
     * Only falls back to a fresh Series when the caller has not supplied one, so
     * `->episode()->for($series)` associates rather than creating a second series.
     */
    public function episode(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WatchType::Episode,
            'series_id' => $attributes['series_id'] ?? Series::factory(),
            'season_number' => fake()->numberBetween(1, 5),
            'episode_number' => fake()->numberBetween(1, 24),
        ]);
    }

    /**
     * A specific episode, so progress totals can be asserted deterministically.
     */
    public function at(int $seasonNumber, int $episodeNumber): static
    {
        return $this->state([
            'type' => WatchType::Episode,
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
        ]);
    }

    public function watchedAt(DateTimeInterface $watchedAt): static
    {
        return $this->state([
            'watched_at' => $watchedAt,
        ]);
    }
}
