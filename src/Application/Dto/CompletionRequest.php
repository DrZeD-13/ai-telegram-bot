<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class CompletionRequest
{
    public function __construct(
        public string $model,
        public string $prompt,
        public bool $stream = false,
    ) {
    }
}
