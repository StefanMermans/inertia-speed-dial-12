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
            'body' => ['payload' => json_encode(['event' => fake()->word()])],
            'files' => null,
            'response_status' => 204,
            'duration_ms' => fake()->numberBetween(10, 500),
        ];
    }
}
