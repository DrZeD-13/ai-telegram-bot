<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatCompletionResult;
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
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Application\UseCase\ProcessIncomingTelegramMessages;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Entity\ProcessedTelegramMessageStatus;
use App\Domain\Repository\ProcessedTelegramMessageRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessIncomingTelegramMessages::class)]
#[CoversMethod(ProcessIncomingTelegramMessages::class, 'execute')]
final class ProcessIncomingTelegramMessagesTest extends TestCase
{
    public function testEmptyInboxDoesNotFlush(): void
    {
        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->expects(self::once())
            ->method('getMessages')
            ->with(null)
            ->willReturn(new IncomingTelegramMessageCollection());
        $telegramBotGateway->expects(self::never())->method('sendMessage');

        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->expects(self::never())->method('listModels');
        $neuralNetworkGateway->expects(self::never())->method('createChatCompletion');

        $repository = $this->createRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::never())->method('persist');
        $unitOfWork->expects(self::never())->method('flush');
        $unitOfWork->expects(self::once())->method('clear');

        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $repository,
            $unitOfWork,
        )->execute();
    }

    public function testSubsequentPollUsesMaxUpdateIdPlusOne(): void
    {
        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->expects(self::once())
            ->method('getMessages')
            ->with(11)
            ->willReturn(new IncomingTelegramMessageCollection());

        $repository = $this->createRepository();
        $repository->method('findMaxUpdateId')->willReturn(10);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::never())->method('flush');
        $unitOfWork->expects(self::once())->method('clear');

        $this->createUseCase(
            $telegramBotGateway,
            $this->createNeuralNetworkGateway(),
            $repository,
            $unitOfWork,
        )->execute();
    }

    public function testChunkOfHundredPersistsEachMessageAndFlushesOncePerChunk(): void
    {
        $incoming = [];
        for ($i = 1; $i <= 101; ++$i) {
            $incoming[] = $this->incomingMessage(updateId: $i, messageId: $i, text: 'вопрос ' . $i);
        }

        $telegramBotGateway = $this->createTelegramBotGateway();
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(...$incoming));
        $telegramBotGateway->method('sendMessage')->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->successfulNeuralNetworkGateway();

        $persistCount = 0;
        $persistCountsAtFlush = [];
        $unitOfWork = $this->createUnitOfWork();
        $unitOfWork->method('persist')->willReturnCallback(
            static function () use (&$persistCount): void {
                ++$persistCount;
            },
        );
        $unitOfWork->method('flush')->willReturnCallback(
            static function () use (&$persistCount, &$persistCountsAtFlush): void {
                $persistCountsAtFlush[] = $persistCount;
            },
        );

        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $unitOfWork,
        )->execute();

        self::assertSame(101, $persistCount);
        self::assertSame([100, 101], $persistCountsAtFlush);
    }

    public function testSkipsEmptyTextAndDuplicateAlreadyInDatabase(): void
    {
        $duplicateIncoming = $this->incomingMessage(updateId: 2, messageId: 20, text: 'повтор');

        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(new IncomingTelegramMessageCollection(
            $this->incomingMessage(updateId: 1, messageId: 10, text: null),
            $duplicateIncoming,
            $this->incomingMessage(updateId: 3, messageId: 30, text: ''),
            $this->incomingMessage(updateId: 4, messageId: 40, chatId: 99, text: 'первый'),
        ));
        $telegramBotGateway->expects(self::once())->method('sendMessage')->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->expects(self::once())
            ->method('createChatCompletion')
            ->willReturn(new ChatCompletionResult('id', 'ответ модели'));

        $existing = new ProcessedTelegramMessage(
            chatId: 1,
            messageId: 20,
            updateId: 2,
            sentAt: new DateTimeImmutable(),
            text: 'уже есть',
        );

        $repository = $this->createRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);
        $repository->method('findOneByChatAndMessageId')->willReturnCallback(
            static function (int $chatId, int $messageId) use ($existing): ?ProcessedTelegramMessage {
                if ($chatId === 1 && $messageId === 20) {
                    return $existing;
                }

                return null;
            },
        );

        $persisted = [];
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $unitOfWork->expects(self::once())->method('flush');
        $unitOfWork->expects(self::exactly(2))->method('clear');

        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $repository,
            $unitOfWork,
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(99, $message->getChatId());
        self::assertSame(40, $message->getMessageId());
        self::assertSame('первый', $message->getText());
    }

    public function testValidationFailureDoesNotCallNeuralNetwork(): void
    {
        $text = str_repeat('я', 1025);
        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage(text: $text)),
        );
        $telegramBotGateway->expects(self::once())
            ->method('sendMessage')
            ->with(1, 'запрос слишком длинный сделайте не более 1024 символов')
            ->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->expects(self::never())->method('listModels');
        $neuralNetworkGateway->expects(self::never())->method('createChatCompletion');

        $persisted = [];
        $unitOfWork = $this->recordingUnitOfWork($persisted);

        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $unitOfWork,
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(1024, mb_strlen((string) $message->getText()));
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
        self::assertSame('запрос слишком длинный сделайте не более 1024 символов', $message->getErrorText());
    }

    public function testNeuralNetworkFailureStoresErrorAndNotifiesUser(): void
    {
        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );
        $telegramBotGateway->expects(self::once())
            ->method('sendMessage')
            ->with(1, 'сервис временно не доступен по пробуйте позднее')
            ->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createNeuralNetworkGateway();
        $neuralNetworkGateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->method('createChatCompletion')->willThrowException(
            new NeuralNetworkTransportException('сбой API'),
        );

        $persisted = [];
        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
        self::assertSame('сервис временно не доступен по пробуйте позднее', $message->getErrorText());
        self::assertSame('Привет', $message->getText());
    }

    public function testEmptyNeuralNetworkReplyIsNeuralNetworkFailure(): void
    {
        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );
        $telegramBotGateway->expects(self::once())
            ->method('sendMessage')
            ->with(1, 'сервис временно не доступен по пробуйте позднее')
            ->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createNeuralNetworkGateway();
        $neuralNetworkGateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->method('createChatCompletion')->willReturn(
            new ChatCompletionResult('id', null),
        );

        $persisted = [];
        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
    }

    public function testFailedAiReplyDeliveryNotifiesUser(): void
    {
        $telegramBotGateway = $this->createTelegramBotGateway();
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage()),
        );
        $telegramBotGateway->method('sendMessage')->willReturnCallback(
            static function (int $chatId, string $text): SentTelegramMessage {
                if ($text === 'ответ модели') {
                    throw new TelegramBotTransportException('не доставлено');
                }

                self::assertSame('сообщение не удалось доставить', $text);

                return new SentTelegramMessage(
                    messageId: 99,
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
                    date: 1,
                    text: $text,
                );
            },
        );

        $neuralNetworkGateway = $this->createNeuralNetworkGateway();
        $neuralNetworkGateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->method('createChatCompletion')->willReturn(
            new ChatCompletionResult('id', 'ответ модели'),
        );

        $persisted = [];
        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
        self::assertSame('сообщение не удалось доставить', $message->getErrorText());
    }

    public function testErrorNotifySendFailureStillPersistsAndFlushes(): void
    {
        $telegramBotGateway = $this->createTelegramBotGateway();
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($this->incomingMessage(text: str_repeat('б', 1025))),
        );
        $telegramBotGateway->method('sendMessage')->willThrowException(
            new TelegramBotTransportException('чат недоступен'),
        );

        $logger = $this->createMock(LoggerService::class);
        $logger->expects(self::once())->method('logException');

        $persisted = [];
        $unitOfWork = $this->recordingUnitOfWork($persisted);

        $this->createUseCase(
            $telegramBotGateway,
            $this->createNeuralNetworkGateway(),
            $this->emptyRepository(),
            $unitOfWork,
            $logger,
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
    }

    public function testSuccessPathStoresFullUserTextAndFacts(): void
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

        $telegramBotGateway = $this->createMock(TelegramBotGateway::class);
        $telegramBotGateway->method('getMessages')->willReturn(
            new IncomingTelegramMessageCollection($incoming),
        );
        $telegramBotGateway->expects(self::once())
            ->method('sendMessage')
            ->with(88, 'ответ модели')
            ->willReturn($this->sentMessage());

        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->expects(self::once())->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->expects(self::once())
            ->method('createChatCompletion')
            ->with(self::callback(static function (ChatCompletionRequest $request): bool {
                self::assertSame('model-1', $request->model);
                self::assertSame(
                    "Короткий вопрос\nответ сделай не больше 1024 символа",
                    $request->messages->all()[0]->content,
                );

                return true;
            }))
            ->willReturn(new ChatCompletionResult('id', 'ответ модели'));

        $persisted = [];
        $this->createUseCase(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $this->emptyRepository(),
            $this->recordingUnitOfWork($persisted),
        )->execute();

        self::assertCount(1, $persisted);
        $message = $persisted[0];
        self::assertInstanceOf(ProcessedTelegramMessage::class, $message);
        self::assertSame(88, $message->getChatId());
        self::assertSame(77, $message->getMessageId());
        self::assertSame(55, $message->getUpdateId());
        self::assertSame('Короткий вопрос', $message->getText());
        self::assertSame('Анна', $message->getUserFirstName());
        self::assertSame('Смирнова', $message->getUserLastName());
        self::assertSame('anna', $message->getUserNickname());
        self::assertEquals(new DateTimeImmutable('@1700000123'), $message->getSentAt());
        self::assertSame(ProcessedTelegramMessageStatus::ProcessedSuccess, $message->getStatus());
        self::assertNull($message->getErrorText());
    }

    /**
     * @param list<ProcessedTelegramMessage> $persisted
     */
    private function recordingUnitOfWork(array &$persisted): UnitOfWork&MockObject
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                self::assertInstanceOf(ProcessedTelegramMessage::class, $entity);
                $persisted[] = $entity;
            },
        );
        $unitOfWork->expects(self::once())->method('flush');
        $unitOfWork->expects(self::exactly(2))->method('clear');

        return $unitOfWork;
    }

    private function emptyRepository(): ProcessedTelegramMessageRepository&Stub
    {
        $repository = $this->createRepository();
        $repository->method('findMaxUpdateId')->willReturn(null);
        $repository->method('findOneByChatAndMessageId')->willReturn(null);

        return $repository;
    }

    private function successfulNeuralNetworkGateway(): NeuralNetworkGateway&Stub
    {
        $neuralNetworkGateway = $this->createNeuralNetworkGateway();
        $neuralNetworkGateway->method('listModels')->willReturn(
            new NeuralNetworkModelCollection(new NeuralNetworkModel('model-1')),
        );
        $neuralNetworkGateway->method('createChatCompletion')->willReturn(
            new ChatCompletionResult('id', 'ответ модели'),
        );

        return $neuralNetworkGateway;
    }

    private function createUseCase(
        TelegramBotGateway $telegramBotGateway,
        NeuralNetworkGateway $neuralNetworkGateway,
        ProcessedTelegramMessageRepository $repository,
        UnitOfWork $unitOfWork,
        ?LoggerService $logger = null,
    ): ProcessIncomingTelegramMessages {
        return new ProcessIncomingTelegramMessages(
            $telegramBotGateway,
            $neuralNetworkGateway,
            $repository,
            $unitOfWork,
            $logger ?? $this->createStub(LoggerService::class),
        );
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

    private function createTelegramBotGateway(): TelegramBotGateway&Stub
    {
        return $this->createStub(TelegramBotGateway::class);
    }

    private function createNeuralNetworkGateway(): NeuralNetworkGateway&Stub
    {
        return $this->createStub(NeuralNetworkGateway::class);
    }

    private function createRepository(): ProcessedTelegramMessageRepository&Stub
    {
        return $this->createStub(ProcessedTelegramMessageRepository::class);
    }

    private function createUnitOfWork(): UnitOfWork&Stub
    {
        return $this->createStub(UnitOfWork::class);
    }
}
