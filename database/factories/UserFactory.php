<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'anilist_access_token' => null,
            'anilist_token_expires_at' => null,
            'plex_account_id' => null,
            'plex_token' => null,
            'tmdb_access_token' => null,
            'tmdb_account_object_id' => null,
            'trakt_access_token' => null,
            'trakt_refresh_token' => null,
            'trakt_token_expires_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has an AniList connection.
     */
    public function withAnilistConnection(): static
    {
        return $this->state(fn (array $attributes) => [
            'anilist_access_token' => fake()->sha256(),
            'anilist_token_expires_at' => now()->addDays(365),
        ]);
    }

    /**
     * Indicate that the user has a Plex connection.
     */
    public function withPlexConnection(?int $accountId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'plex_account_id' => $accountId ?? fake()->randomNumber(8),
            'plex_token' => Str::random(64),
        ]);
    }
}
