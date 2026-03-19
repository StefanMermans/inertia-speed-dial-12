<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Exceptions\InvalidPlexEventException;
use App\Exceptions\PlexEventFileMissedException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PlexEventController
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (($plexEvent = $this->parsePlexEvent($request)) === null) {
            return $this->respond();
        }

        if ($plexEvent->isScrobble() && $this->plexEventIsOwnedByUser($plexEvent, $user)) {
            event(new PlexScrobbleEvent($plexEvent, $user));
        }

        return $this->respond();
    }

    private function plexEventIsOwnedByUser(PlexEventData $plexEvent, User $user): bool
    {
        return $plexEvent->Account->id === $user->plex_account_id;
    }

    private function parsePlexEvent(Request $request): ?PlexEventData
    {
        $this->reportMissingFilesFromResponse($request);

        try {
            $payload = json_decode($request->string('payload')->toString(), true);

            return PlexEventRequestData::factory()
                ->alwaysValidate()
                ->from(['payload' => $payload])
                ->payload;
        } catch (ValidationException $exception) {
            report(new InvalidPlexEventException(
                message: $exception->getMessage(),
                code: $exception->getCode(),
                previous: $exception
            ));
        }

        return null;
    }

    private function reportMissingFilesFromResponse(Request $request): void
    {
        $files = $request->allFiles();

        if (count($files) === 0) {
            return;
        }

        foreach ($files as $file) {
            if (is_array($file)) {
                foreach ($file as $item) {
                    $this->reportMissingFile($item);
                }
            } else {
                $this->reportMissingFile($file);
            }
        }
    }

    private function reportMissingFile(UploadedFile $file): void
    {
        $filepath = $file->store('missed-files', 'local');

        report(new PlexEventFileMissedException($filepath));
    }

    private function respond(): Response
    {
        return response()->noContent();
    }
}
