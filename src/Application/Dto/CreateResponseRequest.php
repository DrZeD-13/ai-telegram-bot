<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class CreateResponseRequest
{
    public function __construct(
        public string $model,
        public string $input,
        public bool $stream = false,
    ) {
    }
}
