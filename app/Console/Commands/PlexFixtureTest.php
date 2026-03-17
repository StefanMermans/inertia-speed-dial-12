<?php

namespace App\Console\Commands;

use App\Support\PlexUrlGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PlexFixtureTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plex:fixtures-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach (glob(base_path('tests/fixtures/plex/*.json')) as $filename) {
            $this->sendFixture($filename);
        }
    }

    protected function sendFixture(string $filename): void {

        $this->info("Sending: ". basename($filename));
        $this->info(PlexUrlGenerator::generate());
        Http::asJson()
            ->acceptJson()
            ->withBody(json_encode(['payload' => file_get_contents($filename)]))
            ->post(PlexUrlGenerator::generate())
            ->throw();
    }
}
