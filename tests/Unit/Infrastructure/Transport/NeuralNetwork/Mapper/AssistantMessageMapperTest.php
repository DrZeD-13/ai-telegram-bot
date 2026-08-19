<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\AssistantMessageMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantMessageMapper::class)]
#[CoversMethod(AssistantMessageMapper::class, 'map')]
final class AssistantMessageMapperTest extends TestCase
{
    public function testMapReturnsContent(): void
    {
        self::assertSame('hello', (new AssistantMessageMapper())->map([
            'role' => 'assistant',
            'content' => 'hello',
        ]));
    }

    public function testMapAllowsMissingContent(): void
    {
        self::assertNull((new AssistantMessageMapper())->map(['role' => 'assistant']));
    }
}
