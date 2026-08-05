<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlexRequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlexRequestLog>
 */
class PlexRequestLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'method' => 'POST',
            'url' => fake()->url(),
            'ip' => fake()->ipv4(),
            'headers' => ['content-type' => ['application/json']],
            'payload' => json_encode(['event' => fake()->word()]),
            'files' => null,
            'response_status' => 204,
            'duration_ms' => fake()->numberBetween(10, 500),
        ];
    }

    public function createdDaysAgo(int $days): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ]);
    }

    /**
     * @param  list<string|false>  $storedPaths
     */
    public function withFiles(array $storedPaths): static
    {
        return $this->state(fn (): array => [
            'files' => array_map(fn (string|false $path): array => [
                'field_name' => 'thumb',
                'original_name' => 'thumb.jpg',
                'mime' => 'image/jpeg',
                'size' => fake()->numberBetween(1000, 50000),
                'stored_path' => $path,
            ], $storedPaths),
        ]);
    }
}
