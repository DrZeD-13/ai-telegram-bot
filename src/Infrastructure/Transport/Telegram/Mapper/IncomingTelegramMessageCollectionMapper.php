<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Exception\TelegramBotTransportException;

final readonly class IncomingTelegramMessageCollectionMapper
{
    public function __construct(
        private IncomingTelegramUpdateMapper $updateMapper,
    ) {
    }

    /**
     * @param list<mixed> $updates
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $updates): IncomingTelegramMessageCollection
    {
        $messages = [];

        foreach ($updates as $update) {
            if (!is_array($update) || !isset($update['message']) || !is_array($update['message'])) {
                continue;
            }

            /** @var array<string, mixed> $update */
            $messages[] = $this->updateMapper->map($update);
        }

        return new IncomingTelegramMessageCollection(...$messages);
    }
}
