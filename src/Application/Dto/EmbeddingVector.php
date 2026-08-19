<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class EmbeddingVector
{
    /**
     * @param list<float> $values
     */
    public function __construct(
        public array $values,
    ) {
    }
}
