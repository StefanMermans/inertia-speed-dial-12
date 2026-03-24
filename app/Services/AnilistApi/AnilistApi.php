<?php

declare(strict_types=1);

namespace App\Services\AnilistApi;

use App\Data\Anilist\AnilistAnimeSeason;
use App\Data\Anilist\AnilistSaveMediaListEntryData;
use App\Data\Anilist\AnilistSaveMediaListEntryVariables;
use App\Data\Anilist\AnilistSearchResult;
use App\Data\Anilist\AnilistTokenData;
use App\Enums\WatchType;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AnilistApi
{
    private const MAX_CHAIN_DEPTH = 50;

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

    public function searchAnime(string $title, WatchType $watchType, ?string $token = null): ?AnilistSearchResult
    {
        $query = <<<'GRAPHQL'
            query ($search: String, $type: MediaType, $formatIn: [MediaFormat]) {
                Media(search: $search, type: $type, format_in: $formatIn) {
                    id
                    idMal
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

        $media = $response->json('data.Media');

        if (! $media) {
            return null;
        }

        return AnilistSearchResult::from($media);
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

    /**
     * Search for an anime and discover all seasons by following the SEQUEL/PREQUEL chain.
     *
     * @return Collection<int, AnilistAnimeSeason>
     */
    public function searchAnimeWithSeasons(string $title, ?string $token = null): Collection
    {
        $entryPoint = $this->searchAnimeWithRelations($title, $token);

        if (! $entryPoint) {
            return collect();
        }

        $firstSeason = $this->walkToFirstSeason($entryPoint, $token);

        return $this->collectSeasonsForward($firstSeason, $token);
    }

    /**
     * Fetch all seasons for an anime by its AniList ID, following the SEQUEL/PREQUEL chain.
     *
     * @return Collection<int, AnilistAnimeSeason>
     */
    public function fetchAnimeSeasons(int $anilistId, ?string $token = null): Collection
    {
        $media = $this->fetchMediaWithRelations($anilistId, $token);

        if (! $media) {
            return collect();
        }

        $firstSeason = $this->walkToFirstSeason($media, $token);

        return $this->collectSeasonsForward($firstSeason, $token);
    }

    /**
     * Fetch a media entry by ID with its relations.
     *
     * @return array{id: int, idMal: ?int, episodes: ?int, format: ?string, relations: array}|null
     */
    public function fetchMediaWithRelations(int $anilistId, ?string $token = null): ?array
    {
        $query = <<<'GRAPHQL'
            query ($id: Int) {
                Media(id: $id, type: ANIME) {
                    id
                    idMal
                    episodes
                    format
                    relations {
                        edges {
                            relationType(version: 2)
                            node {
                                id
                                idMal
                                episodes
                                format
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        $client = $token ? $this->userClient($token) : $this->appClient();

        $response = $client->post('https://graphql.anilist.co', [
            'query' => $query,
            'variables' => ['id' => $anilistId],
        ]);

        $response->throw();

        return $response->json('data.Media');
    }

    /**
     * @return array{id: int, idMal: ?int, episodes: ?int, format: ?string, relations: array}|null
     */
    private function searchAnimeWithRelations(string $title, ?string $token = null): ?array
    {
        $query = <<<'GRAPHQL'
            query ($search: String, $type: MediaType, $formatIn: [MediaFormat]) {
                Media(search: $search, type: $type, format_in: $formatIn) {
                    id
                    idMal
                    episodes
                    format
                    relations {
                        edges {
                            relationType(version: 2)
                            node {
                                id
                                idMal
                                episodes
                                format
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        $client = $token ? $this->userClient($token) : $this->appClient();

        $response = $client->post('https://graphql.anilist.co', [
            'query' => $query,
            'variables' => [
                'search' => $title,
                'type' => 'ANIME',
                'formatIn' => ['TV', 'TV_SHORT', 'SPECIAL', 'OVA', 'ONA'],
            ],
        ]);

        $response->throw();

        return $response->json('data.Media');
    }

    /**
     * Walk backward via PREQUEL relations to find the first season.
     *
     * @param  array{id: int, idMal: ?int, episodes: ?int, format: ?string, relations: array}  $media
     * @return array{id: int, idMal: ?int, episodes: ?int, format: ?string, relations: array}
     */
    private function walkToFirstSeason(array $media, ?string $token): array
    {
        $visited = [$media['id']];

        while ($prequel = $this->findRelation($media, 'PREQUEL')) {
            if (in_array($prequel['id'], $visited, true) || count($visited) >= self::MAX_CHAIN_DEPTH) {
                break;
            }

            $visited[] = $prequel['id'];
            $fetched = $this->fetchMediaWithRelations($prequel['id'], $token);

            if (! $fetched) {
                break;
            }

            $media = $fetched;
        }

        return $media;
    }

    /**
     * Collect all seasons by walking forward via SEQUEL relations from the first season.
     *
     * @param  array{id: int, idMal: ?int, episodes: ?int, format: ?string, relations: array}  $firstSeason
     * @return Collection<int, AnilistAnimeSeason>
     */
    private function collectSeasonsForward(array $firstSeason, ?string $token): Collection
    {
        $seasons = collect();
        $seasonNumber = 1;
        $current = $firstSeason;
        $visited = [];

        while ($current) {
            if (in_array($current['id'], $visited, true) || count($visited) >= self::MAX_CHAIN_DEPTH) {
                break;
            }

            $visited[] = $current['id'];

            $seasons->put($seasonNumber, new AnilistAnimeSeason(
                id: $current['id'],
                idMal: $current['idMal'] ?? null,
                episodes: $current['episodes'] ?? null,
                format: $current['format'] ?? null,
            ));

            $seasonNumber++;

            $sequel = $this->findRelation($current, 'SEQUEL');

            if (! $sequel) {
                break;
            }

            $current = $this->fetchMediaWithRelations($sequel['id'], $token);
        }

        return $seasons;
    }

    /**
     * Find a related media entry by relation type.
     *
     * @param  array{relations: array}  $media
     * @return array{id: int, idMal: ?int, episodes: ?int, format: ?string}|null
     */
    private function findRelation(array $media, string $relationType): ?array
    {
        $edges = $media['relations']['edges'] ?? [];

        foreach ($edges as $edge) {
            if (($edge['relationType'] ?? null) === $relationType) {
                return $edge['node'];
            }
        }

        return null;
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
