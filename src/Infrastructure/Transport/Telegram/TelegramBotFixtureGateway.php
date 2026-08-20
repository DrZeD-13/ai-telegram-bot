<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Dto\TelegramChat;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;
use App\Application\Logger\LoggerService;
use App\Application\Port\TelegramBotGateway;
use App\Domain\Exception\CoreException;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageCollectionMapper;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class TelegramBotFixtureGateway implements TelegramBotGateway
{
    public function __construct(
        private IncomingTelegramMessageCollectionMapper $incomingMessagesMapper,
        private LoggerService $logger,
        #[Autowire('%kernel.project_dir%/fixtures/telegram/get_updates.json')]
        private string $incomingUpdatesPath,
        #[Autowire('%kernel.project_dir%/var/telegram-fixtures/sent.json')]
        private string $sentMessagesPath,
    ) {
    }

    /**
     * @throws TelegramBotTransportException
     */
    public function getMessages(?int $offset = null): IncomingTelegramMessageCollection
    {
        try {
            $updates = $this->loadUpdates();
            if ($offset !== null) {
                $updates = array_values(array_filter(
                    $updates,
                    static function (mixed $update) use ($offset): bool {
                        return is_array($update)
                            && isset($update['update_id'])
                            && is_int($update['update_id'])
                            && $update['update_id'] >= $offset;
                    },
                ));
            }

            return $this->incomingMessagesMapper->map($updates);
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TelegramBotTransportException(
                message: 'Failed to load Telegram fixture updates.',
                previous: $exception,
            );
        }
    }

    /**
     * @throws TelegramBotTransportException
     * @throws TelegramBotValidationException
     */
    public function sendMessage(int $chatId, string $text): SentTelegramMessage
    {
        if (trim($text) === '') {
            throw new TelegramBotValidationException('Message text must not be blank.');
        }

        try {
            $sent = new SentTelegramMessage(
                messageId: $this->nextSentMessageId(),
                from: null,
                chat: new TelegramChat(
                    id: $chatId,
                    type: 'private',
                    title: null,
                    username: null,
                    firstName: null,
                    lastName: null,
                    isForum: null,
                    isDirectMessages: null,
                ),
                date: time(),
                text: $text,
            );
            $this->appendSentMessage($sent);
            $this->logger->info('Telegram fixture: message stored instead of sending to Bot API.', [
                'chatId' => (string) $chatId,
                'messageId' => (string) $sent->messageId,
                'path' => $this->sentMessagesPath,
            ]);

            return $sent;
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TelegramBotTransportException(
                message: 'Failed to store a Telegram fixture outgoing message.',
                previous: $exception,
            );
        }
    }

    /**
     * @return list<mixed>
     *
     * @throws TelegramBotTransportException
     * @throws JsonException
     */
    private function loadUpdates(): array
    {
        if (!is_file($this->incomingUpdatesPath)) {
            throw new TelegramBotTransportException(sprintf(
                'Telegram fixture file not found: %s',
                $this->incomingUpdatesPath,
            ));
        }

        $contents = file_get_contents($this->incomingUpdatesPath);
        if ($contents === false) {
            throw new TelegramBotTransportException(sprintf(
                'Unable to read Telegram fixture file: %s',
                $this->incomingUpdatesPath,
            ));
        }

        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new TelegramBotTransportException('Telegram fixture JSON must be an object or a list of updates.');
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        $result = $payload['result'] ?? null;
        if (!is_array($result)) {
            throw new TelegramBotTransportException('Telegram fixture JSON is missing a result array.');
        }

        return array_values($result);
    }

    /**
     * @throws JsonException
     * @throws TelegramBotTransportException
     */
    private function appendSentMessage(SentTelegramMessage $sent): void
    {
        $messages = $this->readSentMessages();
        $messages[] = [
            'message_id' => $sent->messageId,
            'chat_id' => $sent->chat->id,
            'date' => $sent->date,
            'text' => $sent->text,
        ];

        $directory = dirname($this->sentMessagesPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new TelegramBotTransportException(sprintf(
                'Unable to create Telegram fixture output directory: %s',
                $directory,
            ));
        }

        $written = file_put_contents(
            $this->sentMessagesPath,
            json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
        if ($written === false) {
            throw new TelegramBotTransportException(sprintf(
                'Unable to write Telegram fixture outgoing messages: %s',
                $this->sentMessagesPath,
            ));
        }
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readSentMessages(): array
    {
        if (!is_file($this->sentMessagesPath)) {
            return [];
        }

        $contents = file_get_contents($this->sentMessagesPath);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return [];
        }

        $messages = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $messages[] = $item;
        }

        return $messages;
    }

    /**
     * @throws JsonException
     */
    private function nextSentMessageId(): int
    {
        return count($this->readSentMessages()) + 1;
    }
}
