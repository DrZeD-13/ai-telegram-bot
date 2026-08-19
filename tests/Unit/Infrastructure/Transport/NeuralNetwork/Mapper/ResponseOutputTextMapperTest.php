<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseOutputTextMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseOutputTextMapper::class)]
#[CoversMethod(ResponseOutputTextMapper::class, 'map')]
final class ResponseOutputTextMapperTest extends TestCase
{
    public function testMapPrefersOutputText(): void
    {
        self::assertSame('hello', (new ResponseOutputTextMapper())->map([
            'output_text' => 'hello',
        ]));
    }

    public function testMapReadsNestedOutput(): void
    {
        self::assertSame('nested', (new ResponseOutputTextMapper())->map([
            'output' => [
                ['content' => [['text' => 'nested']]],
            ],
        ]));
    }
}
