<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesUser;
use Illuminate\Console\Command;

class PlexUrlCommand extends Command
{
    use ResolvesUser;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plex:url {user : The user email or ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the Plex webhook URL for a user';

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
