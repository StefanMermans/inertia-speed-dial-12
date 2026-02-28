<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'url' => fake()->url(),
            'icon_path' => 'images/' . fake()->slug() . '.png',
            'background_color' => fake()->hexColor(),
            'no_padding' => false,
        ];
    }

    public function withNoPadding(): static
    {
        return $this->state(['no_padding' => true]);
    }
}
