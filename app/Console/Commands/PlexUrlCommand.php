<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plex:url {user : The user email or ID}')]
#[Description('Display the Plex webhook URL for a user')]
class PlexUrlCommand extends Command
{
    use ResolvesUser;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $url = $user->plexWebhookUrl();

        if (! $url) {
            $this->error('User does not have a Plex connection configured.');

            return self::FAILURE;
        }

        $this->info("Plex url:\n$url");

        return self::SUCCESS;
    }
}
