<?php

declare(strict_types=1);

namespace App\Enums;

enum WatchProgressStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Stale = 'stale';
    case Unmatched = 'unmatched';
}
