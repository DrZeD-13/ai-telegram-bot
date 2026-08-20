<?php

declare(strict_types=1);

namespace App\Domain\Entity;

enum ProcessedTelegramMessageStatus: string
{
    case Pending = 'pending';
    case ProcessedSuccess = 'processed_success';
    case ProcessedError = 'processed_error';
}
