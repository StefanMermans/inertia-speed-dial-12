<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\PlexEvent\PlexGuidData;
use Spatie\LaravelData\Optional;

class ExternalIds
{
    public function __construct(
        public readonly ?int $tmdb = null,
        public readonly ?string $imdb = null,
        public readonly ?int $tvdb = null,
    ) {}

    /**
     * @param  PlexGuidData[]|Optional  $guids
     */
    public static function fromPlexGuids(array|Optional $guids): self
    {
        if ($guids instanceof Optional) {
            return new self;
        }

        $tmdb = null;
        $imdb = null;
        $tvdb = null;

        foreach ($guids as $guid) {
            match (true) {
                str_starts_with($guid->id, 'tmdb://') => $tmdb = (int) substr($guid->id, 7),
                str_starts_with($guid->id, 'imdb://') => $imdb = substr($guid->id, 7),
                str_starts_with($guid->id, 'tvdb://') => $tvdb = (int) substr($guid->id, 7),
                default => null,
            };
        }

        return new self($tmdb, $imdb, $tvdb);
    }

    /** @return array{tmdb_id: int|null, imdb_id: string|null, tvdb_id: int|null} */
    public function toDatabaseArray(): array
    {
        return [
            'tmdb_id' => $this->tmdb,
            'imdb_id' => $this->imdb,
            'tvdb_id' => $this->tvdb,
        ];
    }

    /** @return array{tmdb?: int, imdb?: string, tvdb?: int} */
    public function toTraktArray(): array
    {
        return array_filter([
            'tmdb' => $this->tmdb,
            'imdb' => $this->imdb,
            'tvdb' => $this->tvdb,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
