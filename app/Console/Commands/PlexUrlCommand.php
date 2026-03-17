<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\PlexTokenNotConfiguredException;
use App\Support\PlexUrlGenerator;
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
        try {
            $url = PlexUrlGenerator::generate();
            $this->info("Plex url:\n$url");

            return self::SUCCESS;
        } catch (PlexTokenNotConfiguredException) {
            $this->error('Plex Token is not configured');

            return self::FAILURE;
        }
    }
}
