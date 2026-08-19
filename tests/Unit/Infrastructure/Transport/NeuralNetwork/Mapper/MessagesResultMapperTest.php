<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesContentBlockMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessagesResultMapper::class)]
#[CoversMethod(MessagesResultMapper::class, 'map')]
final class MessagesResultMapperTest extends TestCase
{
    public function testMapConcatenatesTextBlocks(): void
    {
        $result = (new MessagesResultMapper(new MessagesContentBlockMapper()))->map([
            'id' => 'msg-1',
            'content' => [
                ['type' => 'text', 'text' => 'Hel'],
                ['type' => 'text', 'text' => 'lo'],
            ],
        ]);

        self::assertSame('msg-1', $result->id);
        self::assertSame('Hello', $result->text);
    }

    public function testMapAcceptsStringContent(): void
    {
        $result = (new MessagesResultMapper(new MessagesContentBlockMapper()))->map([
            'content' => 'plain',
        ]);

        self::assertSame('plain', $result->text);
    }
}
