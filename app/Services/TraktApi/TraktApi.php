<?php

declare(strict_types=1);

namespace App\Services\TraktApi;

use App\Data\Trakt\TraktSyncHistoryData;
use App\Data\Trakt\TraktTokenData;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TraktApi
{
    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $baseUrl;

    public function __construct()
    {
        $this->clientId = (string) config('services.trakt.client_id');
        $this->clientSecret = (string) config('services.trakt.client_secret');
        $this->baseUrl = (string) config('services.trakt.base_url');
    }

    public function getAuthorizeUrl(string $state): string
    {
        return 'https://trakt.tv/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => route('trakt.callback'),
            'state' => $state,
        ]);
    }

    public function exchangeCodeForToken(string $code): TraktTokenData
    {
        $response = $this->appClient()
            ->post('/oauth/token', [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => route('trakt.callback'),
                'grant_type' => 'authorization_code',
            ]);

        $response->throw();

        return TraktTokenData::from($response->json());
    }

    public function refreshToken(string $refreshToken): TraktTokenData
    {
        $response = $this->appClient()
            ->post('/oauth/token', [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => route('trakt.callback'),
                'grant_type' => 'refresh_token',
            ]);

        $response->throw();

        return TraktTokenData::from($response->json());
    }

    public function resolveUserAccessToken(User $user): ?string
    {
        if (! $user->trakt_token_expires_at?->isPast()) {
            return $user->trakt_access_token;
        }

        try {
            $tokenData = $this->refreshToken($user->trakt_refresh_token);
        } catch (RequestException) {
            return null;
        }

        $user->forceFill([
            'trakt_access_token' => $tokenData->access_token,
            'trakt_refresh_token' => $tokenData->refresh_token,
            'trakt_token_expires_at' => now()->addSeconds($tokenData->expires_in),
        ])->save();

        return $tokenData->access_token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addToHistory(string $token, array $payload): TraktSyncHistoryData
    {
        $response = $this->userClient($token)
            ->post('/sync/history', $payload);

        $response->throw();

        return TraktSyncHistoryData::from($response->json());
    }

    public function revokeToken(string $token): void
    {
        $response = $this->appClient()
            ->post('/oauth/revoke', [
                'token' => $token,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        $response->throw();
    }

    private function appClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'trakt-api-key' => $this->clientId,
                'trakt-api-version' => '2',
            ]);
    }

    private function userClient(string $token): PendingRequest
    {
        return $this->appClient()
            ->withToken($token);
    }
}
