<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Dto;

use App\Application\Dto\ChatMessage;
use App\Application\Dto\ToolCall;
use App\Application\Dto\ToolCallCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatMessage::class)]
#[CoversMethod(ChatMessage::class, 'jsonSerialize')]
final class ChatMessageTest extends TestCase
{
    public function testUserMessageSerializesRoleAndContent(): void
    {
        self::assertSame(
            ['role' => 'user', 'content' => 'привет'],
            (new ChatMessage('user', 'привет'))->jsonSerialize(),
        );
    }

    public function testAssistantToolCallsAreSerialized(): void
    {
        $message = new ChatMessage(
            role: 'assistant',
            content: null,
            toolCalls: new ToolCallCollection(new ToolCall('call_1', 'shell', '{"command":"ls"}')),
        );

        $serialized = $message->jsonSerialize();
        self::assertSame('assistant', $serialized['role']);
        self::assertArrayNotHasKey('content', $serialized);
        self::assertInstanceOf(ToolCallCollection::class, $serialized['tool_calls']);
        $calls = $serialized['tool_calls']->all();
        self::assertCount(1, $calls);
        self::assertSame('call_1', $calls[0]->id);
        self::assertSame('shell', $calls[0]->name);
    }

    public function testToolResultSerializesCallId(): void
    {
        $message = new ChatMessage(role: 'tool', content: 'exit_code: 0', toolCallId: 'call_1');

        self::assertSame(
            [
                'role' => 'tool',
                'content' => 'exit_code: 0',
                'tool_call_id' => 'call_1',
            ],
            $message->jsonSerialize(),
        );
    }
}
