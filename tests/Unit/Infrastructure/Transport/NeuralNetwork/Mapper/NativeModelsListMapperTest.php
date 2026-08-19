<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\NativeModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NeuralNetworkModelMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeModelsListMapper::class)]
#[CoversMethod(NativeModelsListMapper::class, 'map')]
final class NativeModelsListMapperTest extends TestCase
{
    public function testMapReadsModelsKey(): void
    {
        $collection = (new NativeModelsListMapper(new NeuralNetworkModelMapper()))->map([
            'models' => [
                ['id' => 'a'],
                'b',
            ],
        ]);

        self::assertCount(2, $collection);
        self::assertSame('a', $collection->all()[0]->id);
        self::assertSame('b', $collection->all()[1]->id);
    }

    public function testMapReturnsEmptyCollection(): void
    {
        $collection = (new NativeModelsListMapper(new NeuralNetworkModelMapper()))->map([]);

        self::assertCount(0, $collection);
    }
}
