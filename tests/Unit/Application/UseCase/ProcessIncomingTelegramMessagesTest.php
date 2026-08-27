<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Dto\ChatMessage;
use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\NeuralNetworkModel;
use App\Application\Dto\NeuralNetworkModelCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Dto\TelegramChat;
use App\Application\Dto\TelegramUser;
use App\Application\Exception\NeuralNetworkTransportException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\AiAgent;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Application\Service\TelegramMessageSplitter;
use App\Application\UseCase\ProcessIncomingTelegramMessages;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Entity\ProcessedTelegramMessageStatus;
use App\Domain\Repository\ConversationMessageRepository;
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
    private const string PROCESSING_NOTICE = 'Запрос обрабатывается, пожалуйста подождите…';
    private const string RESET_NOTICE = 'Сессия сброшена. Можете начать новый диалог.';

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

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::never())->method('run');

        $repository = $this->createProcessedRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::never())->method('persist');
        $unitOfWork->expects(self::never())->method('flush');
        $unitOfWork->expects(self::once())->method('clear');

        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            agent: $agent,
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

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::never())->method('run');

        $conversationRepository = $this->createMock(ConversationMessageRepository::class);
        $conversationRepository->expects(self::once())->method('deleteByChatId')->with(42)->willReturn(3);

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([self::RESET_NOTICE], $sent);
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

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())->method('run')->willReturn('ответ агента');

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
            agent: $agent,
            processedRepository: $repository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        // processing notice + reply for the single accepted message
        self::assertSame([self::PROCESSING_NOTICE, 'ответ агента'], $sent);

        $processed = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof ProcessedTelegramMessage,
        ));
        self::assertCount(1, $processed);
        self::assertSame(99, $processed[0]->getChatId());
        self::assertSame('первый', $processed[0]->getText());
    }

    public function testSuccessSendsProcessingNoticeThenReplyAndStoresConversation(): void
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

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())
            ->method('run')
            ->with(
                self::callback(static function (array $conversation): bool {
                    self::assertInstanceOf(ChatMessage::class, $conversation[0]);
                    self::assertSame('system', $conversation[0]->role);
                    $last = $conversation[count($conversation) - 1];
                    self::assertInstanceOf(ChatMessage::class, $last);
                    self::assertSame('user', $last->role);
                    self::assertSame('Короткий вопрос', $last->content);

                    return true;
                }),
                'model-1',
            )
            ->willReturn('ответ агента');

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame(
            [
                ['chatId' => 88, 'text' => self::PROCESSING_NOTICE],
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

        $conversation = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof ConversationMessage,
        ));
        self::assertCount(2, $conversation);
        self::assertSame('user', $conversation[0]->getRole());
        self::assertSame('Короткий вопрос', $conversation[0]->getContent());
        self::assertSame('assistant', $conversation[1]->getRole());
        self::assertSame('ответ агента', $conversation[1]->getContent());
    }

    public function testHistoryIsIncludedInConversation(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(chatId: 5, text: 'третий вопрос'),
        ));
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())
            ->method('run')
            ->with(self::callback(static function (array $conversation): bool {
                self::assertCount(4, $conversation); // system + 2 history + current user
                self::assertInstanceOf(ChatMessage::class, $conversation[0]);
                self::assertInstanceOf(ChatMessage::class, $conversation[1]);
                self::assertInstanceOf(ChatMessage::class, $conversation[2]);
                self::assertInstanceOf(ChatMessage::class, $conversation[3]);
                self::assertSame('system', $conversation[0]->role);
                self::assertSame('user', $conversation[1]->role);
                self::assertSame('прошлый вопрос', $conversation[1]->content);
                self::assertSame('assistant', $conversation[2]->role);
                self::assertSame('прошлый ответ', $conversation[2]->content);

                return true;
            }))
            ->willReturn('ответ агента');

        $conversationRepository = $this->createStub(ConversationMessageRepository::class);
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection(
            new ConversationMessage(5, 'user', 'прошлый вопрос'),
            new ConversationMessage(5, 'assistant', 'прошлый ответ'),
        ));

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();
    }

    public function testLongAnswerIsSplitIntoNumberedParts(): void
    {
        $answer = str_repeat('a', 9000);

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

        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willReturn($answer);

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        // processing notice + 3 reply parts
        self::assertCount(4, $sent);
        self::assertSame(self::PROCESSING_NOTICE, $sent[0]);
        self::assertStringStartsWith("1 из 3\n\n", $sent[1]);
        self::assertStringStartsWith("2 из 3\n\n", $sent[2]);
        self::assertStringStartsWith("3 из 3\n\n", $sent[3]);

        $rebuilt = ($sent[1] ?? '') . ($sent[2] ?? '') . ($sent[3] ?? '');
        $rebuilt = str_replace(["1 из 3\n\n", "2 из 3\n\n", "3 из 3\n\n"], '', $rebuilt);
        self::assertSame($answer, $rebuilt);
    }

    public function testNoLengthLimitOnUserText(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(text: str_repeat('я', 5000)),
        ));
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())->method('run')->willReturn('ответ агента');

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        $processed = $this->firstProcessed($persisted);
        self::assertSame(5000, mb_strlen((string) $processed->getText()));
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedSuccess, $processed->getStatus());
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

        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willThrowException(new NeuralNetworkTransportException('сбой API'));

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([self::PROCESSING_NOTICE, 'сервис временно не доступен по пробуйте позднее'], $sent);
        $processed = $this->firstProcessed($persisted);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $processed->getStatus());
        self::assertSame('сервис временно не доступен по пробуйте позднее', $processed->getErrorText());
    }

    public function testEmptyAgentReplyIsNeuralNetworkFailure(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willReturn('   ');

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        $processed = $this->firstProcessed($persisted);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $processed->getStatus());
    }

    public function testNoAvailableModelIsNeuralNetworkFailure(): void
    {
        $telegramBotGateway = $this->createStub(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createStub(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('listModels')->willReturn(new NeuralNetworkModelCollection());

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::never())->method('run');

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $neuralNetworkGateway,
            agent: $agent,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        $processed = $this->firstProcessed($persisted);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $processed->getStatus());
        self::assertSame('сервис временно не доступен по пробуйте позднее', $processed->getErrorText());
    }

    public function testReplyDeliveryFailureNotifiesUser(): void
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

        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willReturn('ответ агента');

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        $persisted = [];
        $this->createUseCase(
            telegramBotGateway: $telegramBotGateway,
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
            unitOfWork: $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertSame([self::PROCESSING_NOTICE, 'сообщение не удалось доставить'], $sent);
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

        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willReturn('ответ');

        $conversationRepository = $this->createConversationRepository();
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

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
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
            conversationRepository: $conversationRepository,
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
        ?NeuralNetworkGateway $neuralNetworkGateway = null,
        ?ProcessedTelegramMessageRepository $processedRepository = null,
        ?ConversationMessageRepository $conversationRepository = null,
        ?AiAgent $agent = null,
        ?UnitOfWork $unitOfWork = null,
        ?LoggerService $logger = null,
    ): ProcessIncomingTelegramMessages {
        return new ProcessIncomingTelegramMessages(
            $telegramBotGateway ?? $this->createStub(TelegramBotGateway::class),
            $neuralNetworkGateway ?? $this->modelGateway(),
            $processedRepository ?? $this->emptyProcessedRepository(),
            $conversationRepository ?? $this->createConversationRepository(),
            $agent ?? $this->createStub(AiAgent::class),
            new TelegramMessageSplitter(),
            $unitOfWork ?? $this->createStub(UnitOfWork::class),
            $logger ?? $this->createStub(LoggerService::class),
        );
    }

    private function modelGateway(): NeuralNetworkGateway&Stub
    {
        $gateway = $this->createStub(NeuralNetworkGateway::class);
        $gateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );

        return $gateway;
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

    private function createConversationRepository(): ConversationMessageRepository&Stub
    {
        $repository = $this->createStub(ConversationMessageRepository::class);
        $repository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());

        return $repository;
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
