<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatCompletionResultMapper::class)]
#[CoversMethod(ChatCompletionResultMapper::class, 'map')]
final class ChatCompletionResultMapperTest extends TestCase
{
    public function testMapUsesFirstChoice(): void
    {
        $result = (new ChatCompletionResultMapper(
            new ChatCompletionChoiceMapper(new AssistantMessageMapper()),
        ))->map([
            'id' => 'cmpl-1',
            'choices' => [
                ['message' => ['content' => 'first']],
                ['message' => ['content' => 'second']],
            ],
        ]);

        self::assertSame('cmpl-1', $result->id);
        self::assertSame('first', $result->text);
    }

    public function testMapAllowsEmptyChoices(): void
    {
        $result = (new ChatCompletionResultMapper(
            new ChatCompletionChoiceMapper(new AssistantMessageMapper()),
        ))->map(['id' => 'cmpl-2']);

        self::assertSame('cmpl-2', $result->id);
        self::assertNull($result->text);
    }
}
