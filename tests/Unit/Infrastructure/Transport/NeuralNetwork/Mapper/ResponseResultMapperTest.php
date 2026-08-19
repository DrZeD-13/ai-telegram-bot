<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseOutputTextMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ResponseResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseResultMapper::class)]
#[CoversMethod(ResponseResultMapper::class, 'map')]
final class ResponseResultMapperTest extends TestCase
{
    public function testMapBuildsResult(): void
    {
        $result = (new ResponseResultMapper(new ResponseOutputTextMapper()))->map([
            'id' => 'resp-1',
            'output_text' => 'out',
        ]);

        self::assertSame('resp-1', $result->id);
        self::assertSame('out', $result->text);
    }
}
