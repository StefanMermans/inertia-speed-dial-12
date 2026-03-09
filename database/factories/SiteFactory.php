<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Override;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    #[Override]
    public function configure()
    {
        return parent::configure()
            ->afterCreating(static function (Site $site) {
                $site
                    ->addMedia(UploadedFile::fake()->image('image.jpg'))
                    ->toMediaCollection();
            });
    }

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
            'background_color' => fake()->hexColor(),
            'no_padding' => false,
        ];
    }

    public function withoutPadding(): static
    {
        return $this->state(['no_padding' => true]);
    }

    public function withPadding(): static
    {
        return $this->state(['no_padding' => false]);
    }
}
