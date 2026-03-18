<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PlexFixtureTestCommand extends Command
{
    use ResolvesUser;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plex:fixtures-test {user : The user email or ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all Plex fixture files to a user\'s webhook URL';

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

        $webhookUrl = $user->plexWebhookUrl();

        if (! $webhookUrl) {
            $this->error('User does not have a Plex connection configured.');

            return self::FAILURE;
        }

        $files = glob(base_path('tests/fixtures/plex/*.json')) ?: [];

        foreach ($files as $filename) {
            $this->sendFixture($filename, $webhookUrl);
        }

        return self::SUCCESS;
    }

    private function sendFixture(string $filename, string $webhookUrl): void
    {
        $this->info('Sending: '.basename($filename));
        Http::acceptJson()
            ->post($webhookUrl, ['payload' => file_get_contents($filename)])
            ->throw();
    }
}
