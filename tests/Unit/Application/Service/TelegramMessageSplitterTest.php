<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\TelegramMessageSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramMessageSplitter::class)]
#[CoversMethod(TelegramMessageSplitter::class, 'split')]
final class TelegramMessageSplitterTest extends TestCase
{
    public function testShortTextStaysSingle(): void
    {
        $parts = (new TelegramMessageSplitter())->split('короткий ответ');

        self::assertSame(['короткий ответ'], $parts);
    }

    public function testTextAtLimitStaysSingle(): void
    {
        $text = str_repeat('a', 4096);

        $parts = (new TelegramMessageSplitter())->split($text);

        self::assertCount(1, $parts);
    }

    public function testLongTextIsSplitAndReassembles(): void
    {
        $text = str_repeat('a', 9000);

        $parts = (new TelegramMessageSplitter())->split($text);

        self::assertCount(3, $parts);
        foreach ($parts as $part) {
            self::assertLessThanOrEqual(4096, mb_strlen($part));
        }
        self::assertSame($text, implode('', $parts));
    }

    public function testSplitPrefersNewlineBoundaries(): void
    {
        $block = str_repeat('a', 3000) . "\n" . str_repeat('b', 3000);

        $parts = (new TelegramMessageSplitter())->split($block);

        self::assertCount(2, $parts);
        self::assertSame(str_repeat('a', 3000), $parts[0]);
        self::assertSame(str_repeat('b', 3000), $parts[1]);
    }

    public function testMultibyteTextIsNotCorrupted(): void
    {
        $text = str_repeat('я', 8000);

        $parts = (new TelegramMessageSplitter())->split($text);

        self::assertSame($text, implode('', $parts));
        foreach ($parts as $part) {
            self::assertLessThanOrEqual(4096, mb_strlen($part));
        }
    }
}
