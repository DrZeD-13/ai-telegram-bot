<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionResultMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ToolCallCollectionMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatCompletionResultMapper::class)]
#[CoversMethod(ChatCompletionResultMapper::class, 'map')]
final class ChatCompletionResultMapperTest extends TestCase
{
    public function testMapUsesFirstChoice(): void
    {
        $result = $this->mapper()->map([
            'id' => 'cmpl-1',
            'choices' => [
                ['message' => ['content' => 'first']],
                ['message' => ['content' => 'second']],
            ],
        ]);

        self::assertSame('cmpl-1', $result->id);
        self::assertSame('first', $result->text);
        self::assertFalse($result->hasToolCalls());
    }

    public function testMapAllowsEmptyChoices(): void
    {
        $result = $this->mapper()->map(['id' => 'cmpl-2']);

        self::assertSame('cmpl-2', $result->id);
        self::assertNull($result->text);
        self::assertFalse($result->hasToolCalls());
    }

    public function testMapExtractsToolCalls(): void
    {
        $result = $this->mapper()->map([
            'id' => 'cmpl-3',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'shell', 'arguments' => '{"command":"ls"}'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($result->hasToolCalls());
        self::assertNotNull($result->toolCalls);
        $calls = $result->toolCalls->all();
        self::assertCount(1, $calls);
        self::assertSame('call_1', $calls[0]->id);
        self::assertSame('shell', $calls[0]->name);
        self::assertSame('{"command":"ls"}', $calls[0]->arguments);
    }

    private function mapper(): ChatCompletionResultMapper
    {
        return new ChatCompletionResultMapper(
            new ChatCompletionChoiceMapper(new AssistantMessageMapper()),
            new ToolCallCollectionMapper(),
        );
    }
}
