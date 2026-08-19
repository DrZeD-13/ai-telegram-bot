<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class LoadModelResult
{
    public function __construct(
        public ?string $status,
        public ?string $message = null,
    ) {
    }
}
