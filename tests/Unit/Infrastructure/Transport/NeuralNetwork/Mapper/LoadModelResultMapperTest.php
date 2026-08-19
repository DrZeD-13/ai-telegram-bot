<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\LoadModelResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadModelResultMapper::class)]
#[CoversMethod(LoadModelResultMapper::class, 'map')]
final class LoadModelResultMapperTest extends TestCase
{
    public function testMapReadsStatusAndMessage(): void
    {
        $result = (new LoadModelResultMapper())->map([
            'status' => 'loaded',
            'message' => 'ok',
        ]);

        self::assertSame('loaded', $result->status);
        self::assertSame('ok', $result->message);
    }

    public function testMapFallsBackToType(): void
    {
        $result = (new LoadModelResultMapper())->map(['type' => 'success']);

        self::assertSame('success', $result->status);
        self::assertNull($result->message);
    }
}
