<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class NeuralNetworkModel
{
    public function __construct(
        public string $id,
        public ?string $object = null,
        public ?string $ownedBy = null,
    ) {
    }
}
