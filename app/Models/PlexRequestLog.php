<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlexRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlexRequestLog extends Model
{
    /** @use HasFactory<PlexRequestLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'method',
        'url',
        'ip',
        'headers',
        'payload',
        'files',
        'response_status',
        'duration_ms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'files' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
