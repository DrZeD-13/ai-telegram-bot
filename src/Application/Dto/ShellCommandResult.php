<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ShellCommandResult
{
    public function __construct(
        public string $command,
        public string $output,
        public string $errorOutput,
        public int $exitCode,
        public bool $timedOut = false,
    ) {
    }
}
