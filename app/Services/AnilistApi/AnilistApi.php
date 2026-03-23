<?php

declare(strict_types=1);

namespace App\Services\AnilistApi;

use App\Data\Anilist\AnilistSaveMediaListEntryData;
use App\Data\Anilist\AnilistSaveMediaListEntryVariables;
use App\Data\Anilist\AnilistTokenData;
use App\Enums\WatchType;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AnilistApi
{
    private readonly string $clientId;

    private readonly string $clientSecret;

    public function __construct()
    {
        $this->clientId = (string) config('services.anilist.client_id');
        $this->clientSecret = (string) config('services.anilist.client_secret');
    }

    public function getAuthorizeUrl(string $state): string
    {
        return 'https://anilist.co/api/v2/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => route('anilist.callback'),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function exchangeCodeForToken(string $code): AnilistTokenData
    {
        $response = $this->appClient()
            ->post('https://anilist.co/api/v2/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => route('anilist.callback'),
                'code' => $code,
            ]);

        $response->throw();

        return AnilistTokenData::from($response->json());
    }

    public function resolveUserAccessToken(User $user): ?string
    {
        if ($user->anilist_token_expires_at?->isPast()) {
            return null;
        }

        return $user->anilist_access_token;
    }

    public function searchAnime(string $title, WatchType $watchType, ?string $token = null): ?int
    {
        $query = <<<'GRAPHQL'
            query ($search: String, $type: MediaType, $formatIn: [MediaFormat]) {
                Media(search: $search, type: $type, format_in: $formatIn) {
                    id
                }
            }
            GRAPHQL;

        $formatIn = match ($watchType) {
            WatchType::Episode => ['TV', 'TV_SHORT', 'SPECIAL', 'OVA', 'ONA'],
            WatchType::Movie => ['MOVIE', 'SPECIAL', 'OVA', 'ONA'],
        };

        $client = $token ? $this->userClient($token) : $this->appClient();

        $response = $client
            ->post('https://graphql.anilist.co', [
                'query' => $query,
                'variables' => [
                    'search' => $title,
                    'type' => 'ANIME',
                    'formatIn' => $formatIn,
                ],
            ]);

        $response->throw();

        return $response->json('data.Media.id');
    }

    public function saveMediaListEntry(string $token, AnilistSaveMediaListEntryVariables $variables): AnilistSaveMediaListEntryData
    {
        $query = <<<'GRAPHQL'
            mutation ($mediaId: Int, $status: MediaListStatus, $progress: Int, $completedAt: FuzzyDateInput) {
                SaveMediaListEntry(mediaId: $mediaId, status: $status, progress: $progress, completedAt: $completedAt) {
                    id
                    status
                    progress
                }
            }
            GRAPHQL;

        $response = $this->userClient($token)
            ->post('https://graphql.anilist.co', [
                'query' => $query,
                'variables' => $variables->toArray(),
            ]);

        $response->throw();

        return AnilistSaveMediaListEntryData::from($response->json('data.SaveMediaListEntry'));
    }

    private function appClient(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->connectTimeout(10);
    }

    private function userClient(string $token): PendingRequest
    {
        return $this->appClient()
            ->withToken($token);
    }
}
