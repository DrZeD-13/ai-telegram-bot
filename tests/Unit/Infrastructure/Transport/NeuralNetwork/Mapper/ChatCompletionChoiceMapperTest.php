<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionChoiceMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatCompletionChoiceMapper::class)]
#[CoversMethod(ChatCompletionChoiceMapper::class, 'map')]
final class ChatCompletionChoiceMapperTest extends TestCase
{
    public function testMapReadsNestedMessage(): void
    {
        $text = (new ChatCompletionChoiceMapper(new AssistantMessageMapper()))->map([
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'ok'],
        ]);

        self::assertSame('ok', $text);
    }

    public function testMapAllowsMissingMessage(): void
    {
        self::assertNull((new ChatCompletionChoiceMapper(new AssistantMessageMapper()))->map(['index' => 0]));
    }
}
