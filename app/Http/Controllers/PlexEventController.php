<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PlexEvent\PlexEventPayloadData;

class PlexEventController extends Controller
{
    public function __invoke(PlexEventPayloadData $plexEvent)
    {
    }
}
