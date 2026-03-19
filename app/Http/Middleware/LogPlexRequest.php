<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PlexRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogPlexRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        rescue(function () use ($request, $response, $durationMs): void {
            $headers = $request->headers->all();
            unset($headers['cookie']);

            PlexRequestLog::query()->create([
                'user_id' => Auth::id(),
                'method' => $request->method(),
                'url' => $request->fullUrlWithoutQuery(['token']),
                'ip' => $request->ip(),
                'headers' => $headers,
                'payload' => $request->input('payload'),
                'body' => $request->except([...array_keys($request->allFiles()), 'token']),
                'files' => $this->collectFileMetadata($request),
                'response_status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]);
        });

        return $response;
    }

    /**
     * @return list<array{field_name: string, original_name: string|null, mime: string|null, size: int|false, stored_path: string|false}>|null
     */
    private function collectFileMetadata(Request $request): ?array
    {
        $allFiles = $request->allFiles();

        if (count($allFiles) === 0) {
            return null;
        }

        $metadata = [];

        foreach ($allFiles as $fieldName => $file) {
            $files = is_array($file) ? $file : [$file];

            foreach ($files as $uploadedFile) {
                /** @var UploadedFile $uploadedFile */
                $metadata[] = [
                    'field_name' => $fieldName,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime' => $uploadedFile->getClientMimeType(),
                    'size' => $uploadedFile->getSize(),
                    'stored_path' => $uploadedFile->store('plex-request-logs', 'local'),
                ];
            }
        }

        return $metadata;
    }
}
