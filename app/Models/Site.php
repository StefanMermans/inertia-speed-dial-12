<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class Site extends Model implements HasMedia
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @pest-mutate-ignore
     */
    protected $fillable = [
        'name',
        'url',
        'background_color',
        'no_padding',
    ];

    protected function casts(): array
    {
        return [
            'no_padding' => 'boolean',
        ];
    }

    public function iconUrl(): Attribute
    {
        return Attribute::get(fn () => $this->getFirstMediaUrl());
    }
}
