<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ChatCompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeChatResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeChatResultMapper::class)]
#[CoversMethod(NativeChatResultMapper::class, 'map')]
final class NativeChatResultMapperTest extends TestCase
{
    public function testMapPrefersOutput(): void
    {
        $result = (new NativeChatResultMapper(
            new ChatCompletionChoiceMapper(new AssistantMessageMapper()),
        ))->map([
            'id' => 'n-1',
            'output' => 'native',
            'choices' => [['message' => ['content' => 'other']]],
        ]);

        self::assertSame('n-1', $result->id);
        self::assertSame('native', $result->text);
    }

    public function testMapFallsBackToChoices(): void
    {
        $result = (new NativeChatResultMapper(
            new ChatCompletionChoiceMapper(new AssistantMessageMapper()),
        ))->map([
            'choices' => [['message' => ['content' => 'from-choice']]],
        ]);

        self::assertNull($result->id);
        self::assertSame('from-choice', $result->text);
    }
}
