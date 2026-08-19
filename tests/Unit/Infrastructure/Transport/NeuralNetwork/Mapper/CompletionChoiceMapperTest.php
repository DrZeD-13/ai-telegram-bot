<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionChoiceMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompletionChoiceMapper::class)]
#[CoversMethod(CompletionChoiceMapper::class, 'map')]
final class CompletionChoiceMapperTest extends TestCase
{
    public function testMapReturnsText(): void
    {
        self::assertSame('done', (new CompletionChoiceMapper())->map(['text' => 'done']));
    }

    public function testMapAllowsMissingText(): void
    {
        self::assertNull((new CompletionChoiceMapper())->map([]));
    }
}
