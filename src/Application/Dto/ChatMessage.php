<?php

declare(strict_types=1);

namespace App\Application\Dto;

use JsonSerializable;

final readonly class ChatMessage implements JsonSerializable
{
    public function __construct(
        public string $role,
        public ?string $content = null,
        public ?ToolCallCollection $toolCalls = null,
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['role' => $this->role];

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->toolCalls !== null && $this->toolCalls->count() > 0) {
            $data['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }
}
