<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\MessagesContentBlockMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessagesContentBlockMapper::class)]
#[CoversMethod(MessagesContentBlockMapper::class, 'map')]
final class MessagesContentBlockMapperTest extends TestCase
{
    public function testMapReturnsText(): void
    {
        self::assertSame('block', (new MessagesContentBlockMapper())->map([
            'type' => 'text',
            'text' => 'block',
        ]));
    }

    public function testMapAllowsMissingText(): void
    {
        self::assertNull((new MessagesContentBlockMapper())->map(['type' => 'tool_use']));
    }
}
