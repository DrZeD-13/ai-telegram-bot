<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram;

use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;
use App\Application\Logger\LoggerService;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageCollectionMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramUpdateMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use App\Infrastructure\Transport\Telegram\TelegramBotFixtureGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramBotFixtureGateway::class)]
#[CoversMethod(TelegramBotFixtureGateway::class, 'getMessages')]
#[CoversMethod(TelegramBotFixtureGateway::class, 'sendMessage')]
final class TelegramBotFixtureGatewayTest extends TestCase
{
    private string $incomingPath;

    private string $sentPath;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . '/telegram-fixtures-' . uniqid('', true);
        self::assertTrue(mkdir($directory, 0777, true));
        $this->incomingPath = $directory . '/get_updates.json';
        $this->sentPath = $directory . '/sent.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->incomingPath, $this->sentPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $directory = dirname($this->incomingPath);
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    public function testGetMessagesMapsFixtureUpdatesAndSkipsNonMessages(): void
    {
        $this->writeIncomingFixture([
            'ok' => true,
            'result' => [
                [
                    'update_id' => 1001,
                    'message' => [
                        'message_id' => 10,
                        'chat' => ['id' => 7, 'type' => 'private'],
                        'date' => 1,
                        'text' => 'привет',
                    ],
                ],
                [
                    'update_id' => 1002,
                    'callback_query' => ['id' => 'cb'],
                ],
            ],
        ]);

        $messages = $this->createGateway()->getMessages();

        self::assertCount(1, $messages);
        self::assertSame(1001, $messages->all()[0]->updateId);
        self::assertSame('привет', $messages->all()[0]->text);
    }

    public function testGetMessagesAppliesOffset(): void
    {
        $this->writeIncomingFixture([
            'ok' => true,
            'result' => [
                [
                    'update_id' => 10,
                    'message' => [
                        'message_id' => 1,
                        'chat' => ['id' => 7, 'type' => 'private'],
                        'date' => 1,
                        'text' => 'old',
                    ],
                ],
                [
                    'update_id' => 12,
                    'message' => [
                        'message_id' => 2,
                        'chat' => ['id' => 7, 'type' => 'private'],
                        'date' => 1,
                        'text' => 'new',
                    ],
                ],
            ],
        ]);

        $messages = $this->createGateway()->getMessages(12);

        self::assertCount(1, $messages);
        self::assertSame(12, $messages->all()[0]->updateId);
        self::assertSame('new', $messages->all()[0]->text);
    }

    public function testSendMessageWritesFixtureFile(): void
    {
        $logger = $this->createMock(LoggerService::class);
        $logger->expects(self::once())->method('info');

        $sent = $this->createGateway($logger)->sendMessage(455708771, 'ответ модели');

        self::assertSame(1, $sent->messageId);
        self::assertSame(455708771, $sent->chat->id);
        self::assertSame('ответ модели', $sent->text);
        self::assertFileExists($this->sentPath);

        $payload = json_decode((string) file_get_contents($this->sentPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertIsArray($payload[0]);
        self::assertSame('ответ модели', $payload[0]['text']);
    }

    public function testSendMessageRejectsBlankText(): void
    {
        $this->expectException(TelegramBotValidationException::class);

        $this->createGateway()->sendMessage(1, '   ');
    }

    public function testGetMessagesFailsWhenFixtureFileIsMissing(): void
    {
        $this->expectException(TelegramBotTransportException::class);

        $this->createGateway()->getMessages();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeIncomingFixture(array $payload): void
    {
        file_put_contents(
            $this->incomingPath,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    private function createGateway(?LoggerService $logger = null): TelegramBotFixtureGateway
    {
        $userMapper = new TelegramUserMapper();
        $chatMapper = new TelegramChatMapper();

        return new TelegramBotFixtureGateway(
            new IncomingTelegramMessageCollectionMapper(
                new IncomingTelegramUpdateMapper(
                    new IncomingTelegramMessageMapper($userMapper, $chatMapper),
                ),
            ),
            $logger ?? $this->createStub(LoggerService::class),
            $this->incomingPath,
            $this->sentPath,
        );
    }
}
