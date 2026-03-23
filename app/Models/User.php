<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasAnilistConnection;
use App\Models\Concerns\HasPlexConnection;
use App\Models\Concerns\HasTmdbConnection;
use App\Models\Concerns\HasTraktConnection;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAnilistConnection, HasFactory, HasPlexConnection, HasTmdbConnection, HasTraktConnection, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'anilist_access_token',
        'plex_token',
        'tmdb_access_token',
        'trakt_access_token',
        'trakt_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'anilist_access_token' => 'encrypted',
            'anilist_token_expires_at' => 'datetime',
            'tmdb_access_token' => 'encrypted',
            'trakt_access_token' => 'encrypted',
            'trakt_refresh_token' => 'encrypted',
            'trakt_token_expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<Watch, $this> */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }
}
