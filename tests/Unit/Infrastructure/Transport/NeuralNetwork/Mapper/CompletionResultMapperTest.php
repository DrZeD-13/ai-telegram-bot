<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionChoiceMapper;
use App\Infrastructure\Transport\NeuralNetwork\Mapper\CompletionResultMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompletionResultMapper::class)]
#[CoversMethod(CompletionResultMapper::class, 'map')]
final class CompletionResultMapperTest extends TestCase
{
    public function testMapUsesFirstChoice(): void
    {
        $result = (new CompletionResultMapper(new CompletionChoiceMapper()))->map([
            'id' => 'cmpl-9',
            'choices' => [['text' => 'alpha'], ['text' => 'beta']],
        ]);

        self::assertSame('cmpl-9', $result->id);
        self::assertSame('alpha', $result->text);
    }
}
