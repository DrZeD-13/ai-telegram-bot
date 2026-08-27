# telegram-ai-agent Specification

## Purpose

Превращает одноразовый вызов нейросети в агентный цикл: модель может вызывать MCP-инструмент `shell` на хосте и вести историю диалога по Telegram-чату.

## Requirements

### Requirement: Conversation history is scoped to a Telegram chat

The system SHALL persist user and assistant messages per Telegram chat identifier and MUST include that history (oldest first, capped) in the next agent request. Clearing history MUST apply only to the chat that sent `/new`.

#### Scenario: Follow-up uses previous turns

- **WHEN** a chat already has stored user and assistant messages
- **THEN** the next agent request includes those messages before the new user text

#### Scenario: /new does not affect other chats

- **WHEN** chat A sends `/new`
- **THEN** stored history for chat B is unchanged

### Requirement: MCP shell tool is available to the model

When MCP shell is enabled, every agent chat-completion request MUST advertise a function tool named `shell` whose argument is a command string. The implementation MUST run the command through a host shell with a timeout, capture stdout and stderr, and return that result to the model as a tool message.

#### Scenario: Shell tool is advertised

- **WHEN** MCP shell is enabled and the agent asks the model for the next step
- **THEN** the chat completion request includes a `shell` tool definition

#### Scenario: Command timeout is reported to the model

- **WHEN** a shell command exceeds the configured timeout
- **THEN** the tool result reports a timeout and a non-success exit code
