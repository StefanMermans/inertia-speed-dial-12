<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use Inertia\Inertia;
use Inertia\Response;

class CreateWatchController
{
    public function __invoke(): Response
    {
        return Inertia::render('watches/create');
    }
}
