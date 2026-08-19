<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddingVectorMapper::class)]
#[CoversMethod(EmbeddingVectorMapper::class, 'map')]
final class EmbeddingVectorMapperTest extends TestCase
{
    public function testMapCastsNumbersToFloat(): void
    {
        $vector = (new EmbeddingVectorMapper())->map([
            'embedding' => [1, 0.5, -2],
        ]);

        self::assertSame([1.0, 0.5, -2.0], $vector->values);
    }
}
