<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\NeuralNetworkModelMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(NeuralNetworkModelMapper::class)]
#[CoversMethod(NeuralNetworkModelMapper::class, 'map')]
final class NeuralNetworkModelMapperTest extends TestCase
{
    public function testMapUsesIdAndOptionalFields(): void
    {
        $model = (new NeuralNetworkModelMapper())->map([
            'id' => 'gpt-4',
            'object' => 'model',
            'owned_by' => 'openai',
        ]);

        self::assertSame('gpt-4', $model->id);
        self::assertSame('model', $model->object);
        self::assertSame('openai', $model->ownedBy);
    }

    public function testMapFallsBackToKey(): void
    {
        $model = (new NeuralNetworkModelMapper())->map([
            'key' => 'org/model',
        ]);

        self::assertSame('org/model', $model->id);
        self::assertNull($model->object);
        self::assertNull($model->ownedBy);
    }
}
