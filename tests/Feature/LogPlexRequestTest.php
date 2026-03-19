<?php

declare(strict_types=1);

namespace Tests\Feature\LogPlexRequestTest;

use App\Http\Middleware\LogPlexRequest;
use App\Models\PlexRequestLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

covers(LogPlexRequest::class);

function plexEventUrl(?string $token = null): string
{
    return route('api.plex-event', $token ? ['token' => $token] : []);
}

function buildPayload(): string
{
    return json_encode(['event' => fake()->word(), 'Account' => ['id' => fake()->randomNumber(5)]]);
}

describe('Plex request logging', function () {
    it('creates a log entry for authenticated requests', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $this->assertDatabaseCount(PlexRequestLog::class, 1);

        $log = PlexRequestLog::query()->first();
        expect($log->user_id)->toBe($user->id)
            ->and($log->method)->toBe('POST')
            ->and($log->response_status)->toBe(204)
            ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
    });

    it('stores the url without the token query parameter', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->url)->toContain('plex-event')
            ->and($log->url)->not->toContain($user->plex_token);
    });

    it('stores request headers without cookies', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->headers)->toBeArray()
            ->and($log->headers)->not->toHaveKey('cookie');
    });

    it('stores the raw payload field', function () {
        $user = User::factory()->withPlexConnection()->create();
        $payload = buildPayload();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => $payload,
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->payload)->toBe($payload);
    });

    it('stores null files when no files are uploaded', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->files)->toBeNull();
    });

    it('stores file metadata and saves file to disk', function () {
        Storage::fake('local');

        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
            'thumb' => UploadedFile::fake()->image('thumb.jpg'),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->files)->toBeArray()
            ->and($log->files)->toHaveCount(1)
            ->and($log->files[0]['field_name'])->toBe('thumb')
            ->and($log->files[0]['original_name'])->toBe('thumb.jpg')
            ->and($log->files[0]['stored_path'])->toStartWith('plex-request-logs/');

        Storage::disk('local')->assertExists($log->files[0]['stored_path']);
    });

    it('stores metadata for multiple files', function () {
        Storage::fake('local');

        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
            'thumb' => UploadedFile::fake()->image('thumb.jpg'),
            'art' => UploadedFile::fake()->image('art.png'),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->files)->toHaveCount(2);

        expect(Storage::disk('local')->files('plex-request-logs'))->toHaveCount(2);
    });

    it('handles an array of files under a single key', function () {
        Storage::fake('local');

        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
            'files' => [
                UploadedFile::fake()->image('thumb1.jpg'),
                UploadedFile::fake()->image('thumb2.jpg'),
            ],
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->files)->toHaveCount(2)
            ->and($log->files[0]['field_name'])->toBe('files')
            ->and($log->files[1]['field_name'])->toBe('files');
    });

    it('stores the client ip address', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->ip)->not->toBeNull();
    });

    it('belongs to a user', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();

        $log = PlexRequestLog::query()->first();
        expect($log->user->id)->toBe($user->id);
    });

    it('does not break the request when logging fails', function () {
        Schema::drop('plex_request_logs');

        $user = User::factory()->withPlexConnection()->create();

        $this->post(plexEventUrl($user->plex_token), [
            'payload' => buildPayload(),
        ])->assertSuccessful();
    });
});
