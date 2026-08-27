<?php

declare(strict_types=1);

namespace App\Application\Dto;

use JsonSerializable;

final readonly class ToolCall implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $arguments,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $this->arguments,
            ],
        ];
    }
}
