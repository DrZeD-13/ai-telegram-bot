<?php

declare(strict_types=1);

namespace App\Application\Dto;

use JsonSerializable;

final readonly class ToolDefinition implements JsonSerializable
{
    /**
     * @param array<string, mixed> $parameters JSON Schema of the tool arguments
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
