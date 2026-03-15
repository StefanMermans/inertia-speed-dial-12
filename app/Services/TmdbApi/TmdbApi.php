<?php

declare(strict_types=1);

namespace App\Services\TmdbApi;

use App\Data\Tmdb\TmdbAccessTokenData;
use App\Data\Tmdb\TmdbListItemData;
use App\Data\Tmdb\TmdbRequestTokenData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TmdbApi
{
    private readonly string $baseUrl;

    private readonly string $apiReadAccessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.tmdb.base_url');
        $this->apiReadAccessToken = config('services.tmdb.api_read_access_token');
    }

    public function createRequestToken(string $redirectTo): TmdbRequestTokenData
    {
        $response = $this->appClient()
            ->post('/4/auth/request_token', [
                'redirect_to' => $redirectTo,
            ]);

        $response->throw();

        return TmdbRequestTokenData::from($response->json());
    }

    public function createAccessToken(string $requestToken): TmdbAccessTokenData
    {
        $response = $this->appClient()
            ->post('/4/auth/access_token', [
                'request_token' => $requestToken,
            ]);

        $response->throw();

        return TmdbAccessTokenData::from($response->json());
    }

    public function createList(string $userAccessToken, string $name, string $description = '', bool $public = false): int
    {
        $response = $this->userClient($userAccessToken)
            ->post('/4/list', [
                'name' => $name,
                'description' => $description,
                'iso_639_1' => 'en',
                'iso_3166_1' => 'US',
                'public' => $public,
            ]);

        $response->throw();

        return $response->json('id');
    }

    /**
     * @param  array<int, TmdbListItemData>  $items
     */
    public function addItemsToList(string $userAccessToken, int $listId, array $items): void
    {
        $response = $this->userClient($userAccessToken)
            ->post("/4/list/{$listId}/items", [
                'items' => array_map(
                    fn (TmdbListItemData $item): array => [
                        'media_type' => $item->media_type->value,
                        'media_id' => $item->media_id,
                    ],
                    $items,
                ),
            ]);

        $response->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function getListDetails(string $userAccessToken, int $listId): array
    {
        $response = $this->userClient($userAccessToken)
            ->get("/4/list/{$listId}");

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountLists(string $userAccessToken, string $accountObjectId, int $page = 1): array
    {
        $response = $this->userClient($userAccessToken)
            ->get("/4/account/{$accountObjectId}/lists", [
                'page' => $page,
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<int, TmdbListItemData>  $items
     */
    public function removeItemsFromList(string $userAccessToken, int $listId, array $items): void
    {
        $response = $this->userClient($userAccessToken)
            ->delete("/4/list/{$listId}/items", [
                'items' => array_map(
                    fn (TmdbListItemData $item): array => [
                        'media_type' => $item->media_type->value,
                        'media_id' => $item->media_id,
                    ],
                    $items,
                ),
            ]);

        $response->throw();
    }

    private function appClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($this->apiReadAccessToken);
    }

    private function userClient(string $userAccessToken): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($userAccessToken);
    }
}
