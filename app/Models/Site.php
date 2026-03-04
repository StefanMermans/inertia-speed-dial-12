<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

final class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'url',
        'background_color',
        'no_padding',
    ];

    protected $appends = [
        'icon_url',
    ];

    protected function casts(): array
    {
        return [
            'no_padding' => 'boolean',
        ];
    }

    public function iconUrl(): Attribute
    {
        return Attribute::get(fn() => Storage::disk('public')->url($this->icon_path));
    }
}
