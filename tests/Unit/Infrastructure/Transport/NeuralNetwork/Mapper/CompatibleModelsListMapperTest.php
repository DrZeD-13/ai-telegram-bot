<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompatibleModelsListMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\NeuralNetworkModelMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompatibleModelsListMapper::class)]
#[CoversMethod(CompatibleModelsListMapper::class, 'map')]
final class CompatibleModelsListMapperTest extends TestCase
{
    public function testMapReadsDataKey(): void
    {
        $collection = (new CompatibleModelsListMapper(new NeuralNetworkModelMapper()))->map([
            'data' => [
                ['id' => 'gpt-4', 'object' => 'model'],
            ],
        ]);

        self::assertCount(1, $collection);
        self::assertSame('gpt-4', $collection->all()[0]->id);
        self::assertSame('model', $collection->all()[0]->object);
    }

    public function testMapReturnsEmptyCollection(): void
    {
        $collection = (new CompatibleModelsListMapper(new NeuralNetworkModelMapper()))->map(['object' => 'list']);

        self::assertCount(0, $collection);
    }
}
