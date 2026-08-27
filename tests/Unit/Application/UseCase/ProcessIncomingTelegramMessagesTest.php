<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\ChatTurnMessage;
use App\Application\Dto\ChatTurnMessageCollection;
use App\Application\Dto\ChatTurnResult;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Dto\TelegramChat;
use App\Application\Dto\TelegramUser;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Application\Port\ChatTurnHandler;
use App\Application\UseCase\ProcessIncomingTelegramMessages;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Entity\ProcessedTelegramMessageStatus;
use App\Domain\Repository\ProcessedTelegramMessageRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessIncomingTelegramMessages::class)]
#[CoversMethod(ProcessIncomingTelegramMessages::class, 'execute')]
final class ProcessIncomingTelegramMessagesTest extends TestCase
{
    public function testEmptyInboxDoesNotFlush(): void
    {
        $requestedOffset = 'unset';
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturnCallback(
            function (?int $offset) use (&$requestedOffset): IncomingTelegramMessageCollection {
                $requestedOffset = $offset;

                return new IncomingTelegramMessageCollection();
            },
        );

        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->expects(self::never())->method('reply');

        $repository = $this->createProcessedRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::never())->method('persist');
        $unitOfWork->expects(self::never())->method('flush');
        $unitOfWork->expects(self::once())->method('clear');

        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            processedRepository: $repository,
            unitOfWork: $unitOfWork,
        )->execute();

        self::assertNull($requestedOffset);
    }

    public function testSubsequentPollUsesMaxUpdateIdPlusOne(): void
    {
        $requestedOffset = null;
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturnCallback(
            function (?int $offset) use (&$requestedOffset): IncomingTelegramMessageCollection {
                $requestedOffset = $offset;

                return new IncomingTelegramMessageCollection();
            },
        );

        $repository = $this->createProcessedRepository();
        $repository->method('findMaxUpdateId')->willReturn(10);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::never())->method('flush');
        $unitOfWork->expects(self::once())->method('clear');

        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            processedRepository: $repository,
            unitOfWork: $unitOfWork,
        )->execute();

        self::assertSame(11, $requestedOffset);
    }

    public function testResetCommandClearsConversationWithoutCallingAgent(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(chatId: 42, text: '/new'),
        ));

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                $sent[] = $text;

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(true);
        $handleChatTurn->expects(self::once())->method('resetSession')->with(42);
        $handleChatTurn->expects(self::never())->method('reply');

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([ChatTurnHandler::RESET_NOTICE], $sent);
        self::assertCount(1, $persisted);
        self::assertInstanceOf(ProcessedTelegramMessage::class, $persisted[0]);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedSuccess, $persisted[0]->getStatus());
    }

    public function testSkipsEmptyTextAndDuplicateAlreadyInDatabase(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(updateId: 1, messageId: 10, text: null),
            $this->incomingMessage(updateId: 2, messageId: 20, text: 'повтор'),
            $this->incomingMessage(updateId: 3, messageId: 30, text: ''),
            $this->incomingMessage(updateId: 4, messageId: 40, chatId: 99, text: 'первый'),
        ));

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                $sent[] = $text;

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->successfulChatTurnHandler('ответ агента');

        $repository = $this->createProcessedRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);
        $repository->method('findOneByChatAndMessageId')->willReturnCallback(
            static function (int $chatId, int $messageId): ?ProcessedTelegramMessage {
                if ($chatId === 1 && $messageId === 20) {
                    return new ProcessedTelegramMessage(
                        chatId: 1,
                        messageId: 20,
                        updateId: 2,
                        sentAt: new DateTimeImmutable(),
                        text: 'уже есть',
                    );
                }

                return null;
            },
        );

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            processedRepository: $repository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([ChatTurnHandler::PROCESSING_NOTICE, 'ответ агента'], $sent);
        self::assertCount(1, $persisted);
        self::assertInstanceOf(ProcessedTelegramMessage::class, $persisted[0]);
        self::assertSame(99, $persisted[0]->getChatId());
        self::assertSame('первый', $persisted[0]->getText());
    }

    public function testSuccessSendsProcessingNoticeThenReply(): void
    {
        $incoming = $this->incomingMessage(
            updateId: 55,
            messageId: 77,
            chatId: 88,
            text: 'Короткий вопрос',
            date: 1_700_000_123,
            firstName: 'Анна',
            lastName: 'Смирнова',
            username: 'anna',
        );

        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection($incoming));

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                $sent[] = ['chatId' => $chatId, 'text' => $text];

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->expects(self::once())
            ->method('reply')
            ->with(88, 'Короткий вопрос')
            ->willReturn($this->successResult('ответ агента'));
        $handleChatTurn->expects(self::once())->method('rememberTurn')->with(88, 'Короткий вопрос', 'ответ агента');

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame(
            [
                ['chatId' => 88, 'text' => ChatTurnHandler::PROCESSING_NOTICE],
                ['chatId' => 88, 'text' => 'ответ агента'],
            ],
            $sent,
        );

        $processed = $this->firstProcessed($persisted);
        self::assertSame(88, $processed->getChatId());
        self::assertSame(77, $processed->getMessageId());
        self::assertSame(55, $processed->getUpdateId());
        self::assertSame('Короткий вопрос', $processed->getText());
        self::assertSame('Анна', $processed->getUserFirstName());
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedSuccess, $processed->getStatus());
    }

    public function testLongAnswerIsSentAsNumberedParts(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(chatId: 7, text: 'длинный запрос'),
        ));

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                $sent[] = $text;

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->createStub(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('reply')->willReturn(new ChatTurnResult(
            new ChatTurnMessageCollection(
                new ChatTurnMessage("1 из 3\n\naaa"),
                new ChatTurnMessage("2 из 3\n\nbbb"),
                new ChatTurnMessage("3 из 3\n\nccc"),
            ),
            false,
            'aaabbbccc',
        ));

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame(
            [
                ChatTurnHandler::PROCESSING_NOTICE,
                "1 из 3\n\naaa",
                "2 из 3\n\nbbb",
                "3 из 3\n\nccc",
            ],
            $sent,
        );
    }

    public function testNeuralNetworkFailureStoresErrorAndNotifiesUser(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                $sent[] = $text;

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->createStub(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('reply')->willReturn(new ChatTurnResult(
            new ChatTurnMessageCollection(new ChatTurnMessage(ChatTurnHandler::ERROR_NEURAL_NETWORK)),
            true,
        ));

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame(
            [ChatTurnHandler::PROCESSING_NOTICE, ChatTurnHandler::ERROR_NEURAL_NETWORK],
            $sent,
        );
        $processed = $this->firstProcessed($persisted);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $processed->getStatus());
        self::assertSame(ChatTurnHandler::ERROR_NEURAL_NETWORK, $processed->getErrorText());
    }

    public function testReplyDeliveryFailureNotifiesUserAndDoesNotRememberTurn(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );

        $sent = [];
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            function (int $chatId, string $text) use (&$sent): SentTelegramMessage {
                if ($text === 'ответ агента') {
                    throw new TelegramBotTransportException('не доставлено');
                }

                $sent[] = $text;

                return $this->sentMessage();
            },
        );

        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('reply')->willReturn($this->successResult('ответ агента'));
        $handleChatTurn->expects(self::never())->method('rememberTurn');

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $handleChatTurn,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([ChatTurnHandler::PROCESSING_NOTICE, 'сообщение не удалось доставить'], $sent);
        $processed = $this->firstProcessed($persisted);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $processed->getStatus());
        self::assertSame('сообщение не удалось доставить', $processed->getErrorText());
    }

    public function testChunkOfHundredFlushesOncePerChunk(): void
    {
        $incoming = [];
        for ($i = 1; $i <= 101; ++$i) {
            $incoming[] = $this->incomingMessage(updateId: $i, messageId: $i, chatId: $i, text: 'вопрос ' . $i);
        }

        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(...$incoming));
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $flushCallsAtCount = [];
        $processedCount = 0;
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('persist')->willReturnCallback(
            static function (object $entity) use (&$processedCount): void {
                if ($entity instanceof ProcessedTelegramMessage) {
                    ++$processedCount;
                }
            },
        );
        $unitOfWork->method('flush')->willReturnCallback(
            static function () use (&$processedCount, &$flushCallsAtCount): void {
                $flushCallsAtCount[] = $processedCount;
            },
        );

        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            handleChatTurn: $this->successfulChatTurnHandler('ответ'),
            unitOfWork: $unitOfWork,
        )->execute();

        self::assertSame(101, $processedCount);
        self::assertSame([100, 101], $flushCallsAtCount);
    }

    /**
     * @param list<object> $persisted
     */
    private function firstProcessed(array $persisted): ProcessedTelegramMessage
    {
        foreach ($persisted as $entity) {
            if ($entity instanceof ProcessedTelegramMessage) {
                return $entity;
            }
        }

        self::fail('No ProcessedTelegramMessage was persisted.');
    }

    /**
     * @param list<object> $persisted
     */
    private function recordingUnitOfWork(array &$persisted): UnitOfWork&Stub
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );

        return $unitOfWork;
    }

    private function createUseCase(
        ?TelegramBotGateway $telegramBotGateway = null,
        ?ProcessedTelegramMessageRepository $processedRepository = null,
        ?ChatTurnHandler $handleChatTurn = null,
        ?UnitOfWork $unitOfWork = null,
        ?LoggerService $logger = null,
    ): ProcessIncomingTelegramMessages {
        return new ProcessIncomingTelegramMessages(
            $telegramBotGateway ?? $this->createStub(TelegramBotGateway::class),
            $processedRepository ?? $this->emptyProcessedRepository(),
            $handleChatTurn ?? $this->successfulChatTurnHandler('ответ агента'),
            $unitOfWork ?? $this->createStub(UnitOfWork::class),
            $logger ?? $this->createStub(LoggerService::class),
        );
    }

    private function successfulChatTurnHandler(string $answer): ChatTurnHandler&Stub
    {
        $handleChatTurn = $this->createStub(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('reply')->willReturn($this->successResult($answer));

        return $handleChatTurn;
    }

    private function successResult(string $answer): ChatTurnResult
    {
        return new ChatTurnResult(
            new ChatTurnMessageCollection(new ChatTurnMessage($answer)),
            false,
            $answer,
        );
    }

    private function emptyProcessedRepository(): ProcessedTelegramMessageRepository&Stub
    {
        $repository = $this->createProcessedRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);
        $repository->method('findOneByChatAndMessageId')->willReturn(null);

        return $repository;
    }

    private function createProcessedRepository(): ProcessedTelegramMessageRepository&Stub
    {
        return $this->createStub(ProcessedTelegramMessageRepository::class);
    }

    private function incomingMessage(
        int $updateId = 1,
        int $messageId = 1,
        int $chatId = 1,
        ?string $text = 'Привет',
        int $date = 1_700_000_000,
        ?string $firstName = 'Иван',
        ?string $lastName = 'Петров',
        ?string $username = 'ivan',
    ): IncomingTelegramMessage {
        return new IncomingTelegramMessage(
            updateId: $updateId,
            messageId: $messageId,
            from: new TelegramUser(
                id: 7,
                isBot: false,
                firstName: $firstName ?? '',
                lastName: $lastName,
                username: $username,
                languageCode: null,
                isPremium: null,
                addedToAttachmentMenu: null,
            ),
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
            date: $date,
            text: $text,
        );
    }

    private function sentMessage(): SentTelegramMessage
    {
        return new SentTelegramMessage(
            messageId: 99,
            from: null,
            chat: new TelegramChat(
                id: 1,
                type: 'private',
                title: null,
                username: null,
                firstName: null,
                lastName: null,
                isForum: null,
                isDirectMessages: null,
            ),
            date: 1,
            text: 'ok',
        );
    }
}
