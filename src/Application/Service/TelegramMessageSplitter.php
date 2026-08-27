<?php

declare(strict_types=1);

namespace App\Application\Service;

/**
 * Splits a long reply into Telegram-sized chunks.
 *
 * Telegram rejects messages longer than 4096 characters, so long answers are cut
 * into pieces (preferring newline/whitespace boundaries) that the caller can label
 * as "1 из N", "2 из N" and so on.
 */
final class TelegramMessageSplitter
{
    private const int TELEGRAM_LIMIT = 4096;

    /**
     * Room left for the "N из M" prefix that the caller may prepend.
     */
    private const int CHUNK_LIMIT = 3900;

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        if (mb_strlen($text) <= self::TELEGRAM_LIMIT) {
            return [$text];
        }

        $chunks = [];
        $rest = $text;

        while (mb_strlen($rest) > self::CHUNK_LIMIT) {
            $window = mb_substr($rest, 0, self::CHUNK_LIMIT);
            $breakpoint = $this->breakpoint($window);

            $chunks[] = rtrim(mb_substr($rest, 0, $breakpoint));
            $rest = ltrim(mb_substr($rest, $breakpoint));
        }

        if ($rest !== '') {
            $chunks[] = $rest;
        }

        return $chunks === [] ? [''] : $chunks;
    }

    private function breakpoint(string $window): int
    {
        $newline = mb_strrpos($window, "\n");
        if ($newline !== false && $newline >= (int) (self::CHUNK_LIMIT / 2)) {
            return $newline + 1;
        }

        $space = mb_strrpos($window, ' ');
        if ($space !== false && $space >= (int) (self::CHUNK_LIMIT / 2)) {
            return $space + 1;
        }

        return self::CHUNK_LIMIT;
    }
}
