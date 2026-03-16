<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Exceptions\InvalidPlexEventException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PlexEventController
{
    public function __invoke(Request $request): Response
    {
        if ($request->query('token') !== config('services.plex.webhook_token')) {
            abort(401);
        }

        if (($plexEvent = $this->parsePlexEvent($request)) === null) {
            return $this->respond();
        }

        if ($plexEvent->isScrobble()) {
            Log::debug('Scrobble event');

            $user = User::query()
                ->where('plex_account_id', $plexEvent->Account->id)
                ->first();

            if ($user) {
                event(new PlexScrobbleEvent($plexEvent, $user));
            }
        }

        return $this->respond();
    }

    private function parsePlexEvent(Request $request): ?PlexEventData
    {
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

    private function respond(): Response
    {
        return response()->noContent();
    }
}
