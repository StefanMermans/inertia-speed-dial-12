<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Exceptions\InvalidPlexEventException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PlexEventController extends Controller
{
    public function __invoke(Request $request)
    {
        if (($plexEvent = $this->parsePlexEvent($request)) === null) {
            return $this->respond();
        }

        if ($plexEvent->isScrobble()) {
            event(new PlexScrobbleEvent($plexEvent));
        }

        return $this->respond();
    }

    private function parsePlexEvent(Request $request): ?PlexEventData
    {
        try {
            return PlexEventRequestData::factory()
                ->alwaysValidate()
                ->from($request->all())
                ->payload;
        } catch (ValidationException $exception) {
            report(new InvalidPlexEventException(previous: $exception));
        }

        return null;
    }

    private function respond(): Response
    {
        return response()->noContent();
    }
}
