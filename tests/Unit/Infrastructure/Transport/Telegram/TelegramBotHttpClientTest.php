<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram;

use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageCollectionMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramUpdateMapper;
use App\Infrastructure\Transport\Telegram\Mapper\SentTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use App\Infrastructure\Transport\Telegram\TelegramBotHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(TelegramBotHttpClient::class)]
#[CoversMethod(TelegramBotHttpClient::class, 'getMessages')]
#[CoversMethod(TelegramBotHttpClient::class, 'sendMessage')]
final class TelegramBotHttpClientTest extends TestCase
{
    public function testGetMessagesReturnsTextMessages(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode([
                    'ok' => true,
                    'result' => [
                        [
                            'update_id' => 793810587,
                            'message' => [
                                'message_id' => 1220,
                                'from' => [
                                    'id' => 455708771,
                                    'is_bot' => false,
                                    'first_name' => 'Иван',
                                    'last_name' => 'Иванов',
                                    'username' => 'NickName',
                                    'language_code' => 'ru',
                                ],
                                'chat' => [
                                    'id' => 455708771,
                                    'first_name' => 'Иван',
                                    'last_name' => 'Иванов',
                                    'username' => 'NickName',
                                    'type' => 'private',
                                ],
                                'date' => 1787069706,
                                'text' => 'Тест',
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ['http_code' => 200],
            ),
        ]);

        $messages = $this->createClient($httpClient)->getMessages();

        self::assertCount(1, $messages);
        $message = $messages->all()[0];
        self::assertSame(793810587, $message->updateId);
        self::assertSame(1220, $message->messageId);
        self::assertNotNull($message->from);
        self::assertSame(455708771, $message->from->id);
        self::assertFalse($message->from->isBot);
        self::assertSame('Иван', $message->from->firstName);
        self::assertSame('Иванов', $message->from->lastName);
        self::assertSame('NickName', $message->from->username);
        self::assertSame('ru', $message->from->languageCode);
        self::assertSame(455708771, $message->chat->id);
        self::assertSame('Иван', $message->chat->firstName);
        self::assertSame('Иванов', $message->chat->lastName);
        self::assertSame('NickName', $message->chat->username);
        self::assertSame('private', $message->chat->type);
        self::assertSame(1787069706, $message->date);
        self::assertSame('Тест', $message->text);
    }

    public function testGetMessagesSkipsNonMessageUpdates(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode([
                    'ok' => true,
                    'result' => [
                        [
                            'update_id' => 11,
                            'callback_query' => ['id' => 'cb-1'],
                        ],
                        [
                            'update_id' => 12,
                            'message' => [
                                'message_id' => 2,
                                'chat' => ['id' => 7, 'type' => 'private'],
                                'date' => 1,
                                'text' => 'kept',
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $messages = $this->createClient($httpClient)->getMessages();

        self::assertCount(1, $messages);
        self::assertSame(12, $messages->all()[0]->updateId);
        self::assertSame('kept', $messages->all()[0]->text);
    }

    public function testGetMessagesReturnsEmptyCollection(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['ok' => true, 'result' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $messages = $this->createClient($httpClient)->getMessages();

        self::assertCount(0, $messages);
    }

    public function testGetMessagesSendsTimeoutAndOffset(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/bot{token}/getUpdates', $url);
            self::assertSame(['token' => 'bot-token'], $options['vars']);
            self::assertSame(['timeout' => 0, 'offset' => 21], $options['query']);

            return new MockResponse(
                json_encode(['ok' => true, 'result' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $this->createClient($httpClient)->getMessages(21);
    }

    public function testSendMessageReturnsSentMessage(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/bot{token}/sendMessage', $url);
            self::assertSame(['token' => 'bot-token'], $options['vars']);
            self::assertIsString($options['body']);
            self::assertSame(
                ['chat_id' => 42, 'text' => 'pong'],
                json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR),
            );

            return new MockResponse(
                json_encode([
                    'ok' => true,
                    'result' => [
                        'message_id' => 99,
                        'from' => [
                            'id' => 1,
                            'is_bot' => true,
                            'first_name' => 'TestBot',
                        ],
                        'chat' => [
                            'id' => 42,
                            'type' => 'private',
                        ],
                        'date' => 1787069706,
                        'text' => 'pong',
                    ],
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $sent = $this->createClient($httpClient)->sendMessage(42, 'pong');

        self::assertSame(42, $sent->chat->id);
        self::assertSame(99, $sent->messageId);
        self::assertSame('pong', $sent->text);
    }

    public function testSendMessageThrowsWhenApiReturnsOkFalse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode([
                    'ok' => false,
                    'description' => 'Bad Request: chat not found',
                ], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $this->expectException(TelegramBotTransportException::class);
        $this->expectExceptionMessageIs('Bad Request: chat not found');

        $this->createClient($httpClient)->sendMessage(1, 'hello');
    }

    public function testGetMessagesThrowsOnHttpFailure(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('gateway timeout', ['http_code' => 504]),
        ]);

        $this->expectException(TelegramBotTransportException::class);
        $this->expectExceptionMessageIs('Failed to retrieve incoming Telegram messages. HTTP status 504.');

        $this->createClient($httpClient)->getMessages();
    }

    public function testGetMessagesThrowsOnEmptyTokenWithoutHttpCall(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            self::fail('HTTP must not be called when the bot token is empty.');
        });

        $this->expectException(TelegramBotConfigurationException::class);
        $this->expectExceptionMessageIs('TELEGRAM_BOT_TOKEN must not be empty.');

        $this->createClient($httpClient, botToken: '')->getMessages();
    }

    public function testSendMessageRejectsBlankTextWithoutHttpCall(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            self::fail('HTTP must not be called when the text is blank.');
        });

        $this->expectException(TelegramBotValidationException::class);
        $this->expectExceptionMessageIs('Message text must not be blank.');

        $this->createClient($httpClient)->sendMessage(1, "  \n");
    }

    public function testGetMessagesWrapsUnexpectedThrowable(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{not-json', ['http_code' => 200]),
        ]);

        try {
            $this->createClient($httpClient)->getMessages();
            self::fail('Expected a transport exception.');
        } catch (TelegramBotTransportException $exception) {
            self::assertSame('Failed to retrieve incoming Telegram messages.', $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }
    }

    private function createClient(
        HttpClientInterface $httpClient,
        string $botToken = 'bot-token',
    ): TelegramBotHttpClient {
        $userMapper = new TelegramUserMapper();
        $chatMapper = new TelegramChatMapper();

        return new TelegramBotHttpClient(
            $httpClient,
            $botToken,
            new IncomingTelegramMessageCollectionMapper(
                new IncomingTelegramUpdateMapper(
                    new IncomingTelegramMessageMapper($userMapper, $chatMapper),
                ),
            ),
            new SentTelegramMessageMapper($userMapper, $chatMapper),
        );
    }
}
