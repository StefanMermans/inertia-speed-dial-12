<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlexRequestLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'method', 'url', 'ip', 'headers', 'payload', 'files', 'response_status', 'duration_ms'])]
final class PlexRequestLog extends Model
{
    /** @use HasFactory<PlexRequestLogFactory> */
    use HasFactory;

    /**
     * Prunable rather than MassPrunable: mass pruning skips model events, which
     * would delete the rows and orphan the uploads they point at.
     */
    use Prunable;

    private const RETENTION_DAYS = 7;

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

    /** @return Builder<$this> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    protected function pruning(): void
    {
        foreach ($this->files ?? [] as $file) {
            $storedPath = $file['stored_path'] ?? null;

            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
