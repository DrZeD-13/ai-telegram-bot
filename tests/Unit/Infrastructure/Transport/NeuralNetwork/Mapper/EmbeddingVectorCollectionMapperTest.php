<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorCollectionMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\EmbeddingVectorMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddingVectorCollectionMapper::class)]
#[CoversMethod(EmbeddingVectorCollectionMapper::class, 'map')]
final class EmbeddingVectorCollectionMapperTest extends TestCase
{
    public function testMapReadsData(): void
    {
        $collection = (new EmbeddingVectorCollectionMapper(new EmbeddingVectorMapper()))->map([
            'data' => [
                ['embedding' => [0.1]],
                ['embedding' => [0.2, 0.3]],
            ],
        ]);

        self::assertCount(2, $collection);
        self::assertSame([0.1], $collection->all()[0]->values);
        self::assertSame([0.2, 0.3], $collection->all()[1]->values);
    }

    public function testMapReturnsEmptyCollection(): void
    {
        $collection = (new EmbeddingVectorCollectionMapper(new EmbeddingVectorMapper()))->map([]);

        self::assertCount(0, $collection);
    }
}
