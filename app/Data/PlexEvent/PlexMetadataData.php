<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PlexMetadataData extends Data
{
    public function __construct(
        public readonly string $librarySectionType,
        public readonly string $ratingKey,
        public readonly string $key,
        public readonly string $guid,
        public readonly string $type,
        public readonly string $title,
        public readonly string $librarySectionTitle,
        public readonly int $librarySectionID,
        public readonly string $librarySectionKey,
        public readonly string $summary,
        public readonly int $year,
        public readonly string $thumb,
        public readonly string $art,
        public readonly int $addedAt,
        public readonly int $updatedAt,

        // Present on most events but not guaranteed
        public readonly int|Optional $duration,
        public readonly string|Optional $contentRating,
        public readonly float|Optional $audienceRating,
        public readonly int|Optional $viewCount,
        public readonly int|Optional $lastViewedAt,
        public readonly string|Optional $originallyAvailableAt,
        public readonly string|Optional $audienceRatingImage,
        public readonly string|Optional $chapterSource,
        public readonly int|Optional $viewOffset,

        // Movie-specific fields
        public readonly string|Optional $slug,
        public readonly string|Optional $studio,
        public readonly string|Optional $originalTitle,
        public readonly float|Optional $rating,
        public readonly string|Optional $tagline,
        public readonly int|Optional $skipCount,
        public readonly int|Optional $deletedAt,
        public readonly int|Optional $contentRatingAge,
        public readonly string|Optional $primaryExtraKey,
        public readonly string|Optional $ratingImage,

        // Episode-specific fields
        public readonly string|Optional $parentRatingKey,
        public readonly string|Optional $grandparentRatingKey,
        public readonly string|Optional $parentGuid,
        public readonly string|Optional $grandparentGuid,
        public readonly string|Optional $grandparentSlug,
        public readonly string|Optional $grandparentTitle,
        public readonly string|Optional $parentTitle,
        public readonly string|Optional $grandparentKey,
        public readonly string|Optional $parentKey,
        public readonly int|Optional $index,
        public readonly int|Optional $parentIndex,
        public readonly string|Optional $parentThumb,
        public readonly string|Optional $grandparentThumb,
        public readonly string|Optional $grandparentArt,
        public readonly string|Optional $grandparentTheme,
        public readonly string|Optional $titleSort,

        // PascalCase fields (nested objects from Plex API)
        /**
         * @var PlexImageData[]|Optional
         */
        #[DataCollectionOf(PlexImageData::class)]
        public readonly array|Optional $Image,
        public readonly PlexUltraBlurColorsData|Optional $UltraBlurColors,
        /**
         * @var PlexGuidData[]|Optional
         */
        #[DataCollectionOf(PlexGuidData::class)]
        public readonly array|Optional $Guid,
        /**
         * @var PlexRatingData[]|Optional
         */
        #[DataCollectionOf(PlexRatingData::class)]
        public readonly array|Optional $Rating,
        /**
         * @var PlexTagData[]|Optional
         */
        #[DataCollectionOf(PlexTagData::class)]
        public readonly array|Optional $Genre,
        /**
         * @var PlexTagData[]|Optional
         */
        #[DataCollectionOf(PlexTagData::class)]
        public readonly array|Optional $Country,
        /**
         * @var PlexCrewData[]|Optional
         */
        #[DataCollectionOf(PlexCrewData::class)]
        public readonly array|Optional $Director,
        /**
         * @var PlexCrewData[]|Optional
         */
        #[DataCollectionOf(PlexCrewData::class)]
        public readonly array|Optional $Writer,
        /**
         * @var PlexRoleData[]|Optional
         */
        #[DataCollectionOf(PlexRoleData::class)]
        public readonly array|Optional $Role,
        /**
         * @var PlexCrewData[]|Optional
         */
        #[DataCollectionOf(PlexCrewData::class)]
        public readonly array|Optional $Producer,
        /**
         * @var PlexCommonSenseMediaData[]|Optional
         */
        #[DataCollectionOf(PlexCommonSenseMediaData::class)]
        public readonly array|Optional $CommonSenseMedia,
    ) {}

    public function tmdbId(): ?int
    {
        if ($id = $this->extractGuid('tmdb://')) {
            return (int) $id;
        }

        return null;
    }

    public function imdbId(): ?string
    {
        return $this->extractGuid('imdb://');
    }

    public function tvdbId(): ?int
    {
        if ($id = $this->extractGuid('tvdb://')) {
            return (int) $id;
        }

        return null;
    }

    protected function extractGuid(string $prefix): ?string
    {
        foreach ($this->Guid as $guid) {
            if (Str::startsWith($guid->id, $prefix)) {
                return Str::substr($guid->id, Str::length($prefix));
            }
        }

        return null;
    }
}
