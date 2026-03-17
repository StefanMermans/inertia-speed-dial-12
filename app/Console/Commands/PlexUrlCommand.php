<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PlexUrlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plex:url';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = config('services.plex.webhook_token');

        if ($token === '' || $token === null) {
            $this->error('Plex Token is not configured');

            return self::FAILURE;
        }

        $url = route('api.plex-event', [
            'token' => $token,
        ]);

        $this->info("Plex url:\n$url");

        return self::SUCCESS;
    }
}
