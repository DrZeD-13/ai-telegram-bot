<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;
use App\Application\Port\TelegramBotGateway;
use App\Domain\Exception\CoreException;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageCollectionMapper;
use App\Infrastructure\Transport\Telegram\Mapper\SentTelegramMessageMapper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final readonly class TelegramBotHttpClient implements TelegramBotGateway
{
    public function __construct(
        #[Target('telegram')]
        private HttpClientInterface $httpClient,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private string $botToken,
        private IncomingTelegramMessageCollectionMapper $incomingMessagesMapper,
        private SentTelegramMessageMapper $sentMessageMapper,
    ) {
    }

    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     */
    public function getMessages(?int $offset = null): IncomingTelegramMessageCollection
    {
        try {
            $this->assertConfigured();

            $response = $this->httpClient->request(
                ApiUrlEnum::GetUpdates->method(),
                ApiUrlEnum::GetUpdates->uri(),
                [
                    'vars' => ['token' => $this->botToken],
                    'query' => array_filter(
                        [
                            'timeout' => 0,
                            'offset' => $offset,
                        ],
                        static fn (mixed $value): bool => $value !== null,
                    ),
                ],
            );

            $payload = $this->decodeOkPayload($response, 'Failed to retrieve incoming Telegram messages.');
            $result = $payload['result'] ?? [];
            if (!is_array($result)) {
                throw new TelegramBotTransportException('Telegram getUpdates returned a non-array result.');
            }

            return $this->incomingMessagesMapper->map(array_values($result));
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TelegramBotTransportException(
                message: 'Failed to retrieve incoming Telegram messages.',
                previous: $exception,
            );
        }
    }

    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     * @throws TelegramBotValidationException
     */
    public function sendMessage(int $chatId, string $text): SentTelegramMessage
    {
        try {
            $this->assertConfigured();

            if (trim($text) === '') {
                throw new TelegramBotValidationException('Message text must not be blank.');
            }

            $response = $this->httpClient->request(
                ApiUrlEnum::SendMessage->method(),
                ApiUrlEnum::SendMessage->uri(),
                [
                    'vars' => ['token' => $this->botToken],
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => $text,
                    ],
                ],
            );

            $payload = $this->decodeOkPayload($response, 'Failed to send a Telegram message.');
            $result = $payload['result'] ?? null;
            if (!is_array($result)) {
                throw new TelegramBotTransportException('Telegram sendMessage returned a non-object result.');
            }

            /** @var array<string, mixed> $result */
            return $this->sentMessageMapper->map($result);
        } catch (CoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TelegramBotTransportException(
                message: 'Failed to send a Telegram message.',
                previous: $exception,
            );
        }
    }

    /**
     * @throws TelegramBotConfigurationException
     */
    private function assertConfigured(): void
    {
        if ($this->botToken === '') {
            throw new TelegramBotConfigurationException('TELEGRAM_BOT_TOKEN must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TelegramBotTransportException
     */
    private function decodeOkPayload(ResponseInterface $response, string $transportFailureMessage): array
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TelegramBotTransportException(
                sprintf('%s HTTP status %d.', $transportFailureMessage, $statusCode),
            );
        }

        $payload = $response->toArray();
        if (($payload['ok'] ?? false) !== true) {
            $description = $payload['description'] ?? null;
            $message = is_string($description) && $description !== ''
                ? $description
                : 'Telegram API returned ok: false.';

            throw new TelegramBotTransportException($message);
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new TelegramBotTransportException('Telegram API returned a non-object JSON payload.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
