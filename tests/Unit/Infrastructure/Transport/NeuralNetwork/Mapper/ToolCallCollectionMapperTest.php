<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\ToolCallCollectionMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolCallCollectionMapper::class)]
#[CoversMethod(ToolCallCollectionMapper::class, 'map')]
final class ToolCallCollectionMapperTest extends TestCase
{
    public function testMapReadsFunctionToolCalls(): void
    {
        $collection = (new ToolCallCollectionMapper())->map([
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => ['name' => 'shell', 'arguments' => '{"command":"ls"}'],
                    ],
                ],
            ],
        ]);

        self::assertNotNull($collection);
        self::assertCount(1, $collection);
        $call = $collection->all()[0];
        self::assertSame('call_1', $call->id);
        self::assertSame('shell', $call->name);
        self::assertSame('{"command":"ls"}', $call->arguments);
    }

    public function testMapEncodesObjectArguments(): void
    {
        $collection = (new ToolCallCollectionMapper())->map([
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_2',
                        'function' => ['name' => 'shell', 'arguments' => ['command' => 'pwd']],
                    ],
                ],
            ],
        ]);

        self::assertNotNull($collection);
        self::assertSame('{"command":"pwd"}', $collection->all()[0]->arguments);
    }

    public function testMapReturnsNullWithoutToolCalls(): void
    {
        self::assertNull((new ToolCallCollectionMapper())->map(['message' => ['content' => 'ok']]));
        self::assertNull((new ToolCallCollectionMapper())->map(['index' => 0]));
    }

    public function testMapRejectsMissingFunctionName(): void
    {
        $this->expectException(NeuralNetworkTransportException::class);

        (new ToolCallCollectionMapper())->map([
            'message' => [
                'tool_calls' => [
                    ['function' => ['arguments' => '{}']],
                ],
            ],
        ]);
    }
}
