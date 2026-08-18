<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram;

enum ApiUrlEnum: string
{
    case GetUpdates = 'POST:/bot{token}/getUpdates';
    case SendMessage = 'POST:/bot{token}/sendMessage';

    public function method(): string
    {
        return explode(':', $this->value, 2)[0];
    }

    public function uri(): string
    {
        return explode(':', $this->value, 2)[1];
    }
}
