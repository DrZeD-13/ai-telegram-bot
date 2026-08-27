<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Dto\ChatMessage;
use App\Application\Dto\NeuralNetworkModel;
use App\Application\Dto\NeuralNetworkModelCollection;
use App\Application\Exception\NeuralNetworkTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\AiAgent;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\UnitOfWork;
use App\Application\Service\TelegramMessageSplitter;
use App\Application\UseCase\HandleChatTurn;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Repository\ConversationMessageRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandleChatTurn::class)]
#[CoversMethod(HandleChatTurn::class, 'isResetCommand')]
#[CoversMethod(HandleChatTurn::class, 'resetSession')]
#[CoversMethod(HandleChatTurn::class, 'reply')]
#[CoversMethod(HandleChatTurn::class, 'rememberTurn')]
final class HandleChatTurnTest extends TestCase
{
    public function testIsResetCommandRecognizesSlashNew(): void
    {
        $handler = $this->handler();

        self::assertTrue($handler->isResetCommand('/new'));
        self::assertTrue($handler->isResetCommand(' /new '));
        self::assertTrue($handler->isResetCommand('/new@my_bot'));
        self::assertFalse($handler->isResetCommand('new'));
        self::assertFalse($handler->isResetCommand('/start'));
    }

    public function testResetSessionDeletesHistory(): void
    {
        $conversationRepository = $this->createMock(ConversationMessageRepository::class);
        $conversationRepository->expects(self::once())->method('deleteByChatId')->with(42)->willReturn(3);

        $this->handler(conversationRepository: $conversationRepository)->resetSession(42);
    }

    public function testReplyIncludesHistoryAndCurrentUserText(): void
    {
        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())
            ->method('run')
            ->with(
                self::callback(static function (array $conversation): bool {
                    self::assertCount(4, $conversation);
                    self::assertInstanceOf(ChatMessage::class, $conversation[0]);
                    self::assertInstanceOf(ChatMessage::class, $conversation[1]);
                    self::assertInstanceOf(ChatMessage::class, $conversation[2]);
                    self::assertInstanceOf(ChatMessage::class, $conversation[3]);
                    self::assertSame('system', $conversation[0]->role);
                    self::assertSame('user', $conversation[1]->role);
                    self::assertSame('прошлый вопрос', $conversation[1]->content);
                    self::assertSame('assistant', $conversation[2]->role);
                    self::assertSame('прошлый ответ', $conversation[2]->content);
                    self::assertSame('user', $conversation[3]->role);
                    self::assertSame('третий вопрос', $conversation[3]->content);

                    return true;
                }),
                'model-1',
            )
            ->willReturn('ответ агента');

        $conversationRepository = $this->createStub(ConversationMessageRepository::class);
        $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection(
            new ConversationMessage(5, 'user', 'прошлый вопрос'),
            new ConversationMessage(5, 'assistant', 'прошлый ответ'),
        ));

        $result = $this->handler(
            neuralNetworkGateway: $this->modelGateway(),
            conversationRepository: $conversationRepository,
            agent: $agent,
        )->reply(5, 'третий вопрос');

        self::assertFalse($result->failed);
        self::assertSame('ответ агента', $result->assistantText);
        self::assertSame('ответ агента', $result->messages->all()[0]->text);
    }

    public function testReplySplitsLongAnswer(): void
    {
        $answer = str_repeat('a', 9000);
        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willReturn($answer);

        $result = $this->handler(
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
        )->reply(1, 'длинный запрос');

        self::assertFalse($result->failed);
        self::assertCount(3, $result->messages);
        self::assertStringStartsWith("1 из 3\n\n", $result->messages->all()[0]->text);
        self::assertStringStartsWith("2 из 3\n\n", $result->messages->all()[1]->text);
        self::assertStringStartsWith("3 из 3\n\n", $result->messages->all()[2]->text);
    }

    public function testReplyAcceptsLongUserText(): void
    {
        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::once())->method('run')->willReturn('ответ агента');

        $result = $this->handler(
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
        )->reply(1, str_repeat('я', 5000));

        self::assertFalse($result->failed);
    }

    public function testReplyFailsWhenAgentThrows(): void
    {
        $agent = $this->createStub(AiAgent::class);
        $agent->method('run')->willThrowException(new NeuralNetworkTransportException('сбой API'));

        $result = $this->handler(
            neuralNetworkGateway: $this->modelGateway(),
            agent: $agent,
        )->reply(1, 'Привет');

        self::assertTrue($result->failed);
        self::assertNull($result->assistantText);
        self::assertSame(HandleChatTurn::ERROR_NEURAL_NETWORK, $result->messages->all()[0]->text);
    }

    public function testReplyFailsWhenNoModel(): void
    {
        $neuralNetworkGateway = $this->createStub(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('listModels')->willReturn(new NeuralNetworkModelCollection());

        $agent = $this->createMock(AiAgent::class);
        $agent->expects(self::never())->method('run');

        $result = $this->handler(
            neuralNetworkGateway: $neuralNetworkGateway,
            agent: $agent,
        )->reply(1, 'Привет');

        self::assertTrue($result->failed);
    }

    public function testRememberTurnPersistsUserAndAssistantAndFlushes(): void
    {
        $persisted = [];
        $flushed = false;
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $unitOfWork->method('flush')->willReturnCallback(
            static function () use (&$flushed): void {
                $flushed = true;
            },
        );

        $this->handler(unitOfWork: $unitOfWork)->rememberTurn(9, 'вопрос', 'ответ');

        self::assertTrue($flushed);
        self::assertCount(2, $persisted);
        self::assertInstanceOf(ConversationMessage::class, $persisted[0]);
        self::assertInstanceOf(ConversationMessage::class, $persisted[1]);
        self::assertSame('user', $persisted[0]->getRole());
        self::assertSame('вопрос', $persisted[0]->getContent());
        self::assertSame('assistant', $persisted[1]->getRole());
        self::assertSame('ответ', $persisted[1]->getContent());
        self::assertSame(9, $persisted[0]->getChatId());
    }

    private function handler(
        ?NeuralNetworkGateway $neuralNetworkGateway = null,
        ?ConversationMessageRepository $conversationRepository = null,
        ?AiAgent $agent = null,
        ?UnitOfWork $unitOfWork = null,
    ): HandleChatTurn {
        if ($conversationRepository === null) {
            $conversationRepository = $this->createStub(ConversationMessageRepository::class);
            $conversationRepository->method('findHistoryByChatId')->willReturn(new ConversationMessageCollection());
        }

        return new HandleChatTurn(
            $neuralNetworkGateway ?? $this->modelGateway(),
            $conversationRepository,
            $agent ?? $this->createStub(AiAgent::class),
            new TelegramMessageSplitter(),
            $unitOfWork ?? $this->createStub(UnitOfWork::class),
            $this->createStub(LoggerService::class),
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
}
