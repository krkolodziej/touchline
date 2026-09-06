<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case MatchFinished = 'MATCH_FINISHED';
    case KickOffReminder = 'KICK_OFF_REMINDER';
}
