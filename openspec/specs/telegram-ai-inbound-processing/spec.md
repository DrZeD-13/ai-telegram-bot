# telegram-ai-inbound-processing Specification

## Purpose

Обрабатывает входящие текстовые сообщения Telegram: забирает новые апдейты по курсору из хранилища, ведёт диалог с ИИ-агентом (нейросеть + инструмент MCP shell) и фиксирует успех или ошибку пачками.

## Requirements

### Requirement: Poll new Telegram messages using stored update cursor

The system SHALL load the highest Telegram update identifier already stored for processed messages and SHALL retrieve incoming messages from Telegram using that value as the Bot API offset basis. When no processed message exists, the retrieve MUST omit offset so Telegram returns the current pending inbox. When a stored update identifier exists, the retrieve offset MUST be that identifier plus one so already seen updates are not returned again.

#### Scenario: Empty store starts without offset

- **WHEN** processing is invoked and no processed Telegram message record exists
- **THEN** the system retrieves incoming messages without an offset

#### Scenario: Subsequent poll skips already stored updates

- **WHEN** processing is invoked and at least one processed message record exists
- **THEN** the system retrieves incoming messages with offset equal to the highest stored Telegram update identifier plus one

#### Scenario: Empty inbox ends the run

- **WHEN** Telegram returns no incoming messages
- **THEN** the system does not persist new records and does not treat the empty inbox as a processing error

### Requirement: Process retrieved messages in persistable chunks

The system SHALL walk every retrieved incoming message. It MUST group processed records into chunks of at most 100 messages. New or changed records MUST be registered with the persistence unit of work while the chunk is processed. The system MUST commit the unit of work once after that chunk has been fully processed. It MUST NOT commit storage after every individual message inside a chunk.

#### Scenario: Chunk is committed once

- **WHEN** a chunk of processed messages (success or error) is complete
- **THEN** the system commits those records to storage in one write for that chunk

#### Scenario: Full inbox is covered

- **WHEN** Telegram returns more than 100 incoming messages
- **THEN** the system processes all of them across successive chunks of at most 100

### Requirement: Skip duplicates and messages without text

The system MUST NOT create a second record for a chat identifier and message identifier pair that is already stored. Incoming messages without text MUST be skipped and MUST NOT be sent to the neural network.

#### Scenario: Already stored chat and message id is skipped

- **WHEN** an incoming message has the same chat id and message id as an existing record
- **THEN** the system does not create another record and does not call the neural network for that message

#### Scenario: Message without text is skipped

- **WHEN** an incoming message has no text
- **THEN** the system does not persist a record for it and does not call the neural network

### Requirement: Start a new neural-network session with /new

The system SHALL treat incoming text `/new` (and `/new@botname`) as starting a new neural-network session for that Telegram chat. It MUST keep stored conversation history of the previous session, MUST NOT call the neural network, MUST send the previous session UUID and the new current session UUID so the user can return later, and MUST store a processed-success record for the command.

#### Scenario: /new starts a new session and preserves history

- **WHEN** an incoming message text is `/new` or starts with `/new@`
- **THEN** the system does not delete conversation history of the previous session
- **AND** it does not call the neural network
- **AND** the chat receives the previous session UUID and the new current session UUID

### Requirement: Resume a neural-network session with /open

The system SHALL treat incoming text `/open <uuid>` (and `/open@botname <uuid>`) as switching that Telegram chat to a previously created session owned by the same chat. It MUST NOT call the neural network.

#### Scenario: /open restores a saved session

- **WHEN** an incoming message text is `/open` with a valid session UUID that belongs to this Telegram chat
- **THEN** further user messages in that chat use the restored session history
- **AND** the chat receives the current session UUID

#### Scenario: /open is recognized despite Telegram whitespace

- **WHEN** an incoming message starts with `/open` and a session UUID separated by a regular space, a non-breaking space, or a line break
- **THEN** the system treats it as a resume command
- **AND** it does not send the text to the neural network

#### Scenario: /open rejects invalid or foreign session UUID

- **WHEN** an incoming message text is `/open` with a missing argument, a value that is not a UUID, a UUID that does not exist, or a session UUID owned by another Telegram chat
- **THEN** the chat receives `такого чата не существует`
- **AND** the system does not switch the current session
- **AND** it does not call the neural network

### Requirement: Acknowledge processing before the agent runs

For every non-command incoming text that is processed, the system MUST send `Запрос обрабатывается, пожалуйста подождите…` to the originating chat before invoking the neural network agent. Failure to send this notice MUST NOT abort processing.

#### Scenario: User is told the request is being processed

- **WHEN** a non-empty non-reset incoming message is accepted for processing
- **THEN** the chat receives `Запрос обрабатывается, пожалуйста подождите…` before the agent is called

### Requirement: Accept user text of any length

The system MUST send non-empty incoming text of any length to the neural network agent. It MUST NOT reject a request because of a 1024-character limit and MUST NOT append an instruction that the answer stay under 1024 characters.

#### Scenario: Long user text is accepted

- **WHEN** an incoming message text length is greater than 1024 characters and is non-empty
- **THEN** the system sends that text to the neural network agent

### Requirement: Run the neural network as an agent with MCP shell

When processing a user message, the system SHALL call the neural network as an agent over the chat conversation (system prompt, stored history for that chat, current user text). The agent MUST be able to call a `shell` tool that executes a host shell command through MCP-style shell execution and receive stdout, stderr, and exit code. The agent MUST continue until it produces a final text answer or the neural network call fails.

#### Scenario: Successful answer is delivered

- **WHEN** the agent returns a text reply and Telegram accepts the send
- **THEN** the user receives that reply in the originating chat
- **AND** the stored processed-message record status is processed successfully
- **AND** the user text and the assistant reply are stored in conversation history for that chat

#### Scenario: Agent may execute a shell command

- **WHEN** the neural network returns a tool call named `shell` with a command argument
- **THEN** the system executes that command on the host
- **AND** it sends the command result back to the model before asking for the next step

### Requirement: Split long replies across Telegram messages

When the agent answer does not fit in one Telegram message (4096 characters), the system MUST split it into sequential chat messages. Each part MUST be prefixed with `N из M` (1-based index and total count). A reply that fits in one Telegram message MUST be sent as a single message without that prefix.

#### Scenario: Short reply is a single message

- **WHEN** the agent answer length is at most 4096 characters
- **THEN** the chat receives exactly one reply message with that text and no `N из M` prefix

#### Scenario: Long reply is numbered parts

- **WHEN** the agent answer is longer than 4096 characters
- **THEN** the chat receives two or more messages
- **AND** the first message starts with `1 из M`
- **AND** the last message starts with `M из M`

### Requirement: Failed processing stores an error record and notifies the user

When processing fails for neural network unavailability or failure to deliver the model reply, the system SHALL create a processed-message record filled from the incoming user data, MUST set status to processed with error, MUST store the corresponding error text, and MUST send the user-facing error message to that chat. Persistence of the error record MUST still happen if sending the user-facing error message fails.

User-facing chat messages MUST be:

- neural network unavailable or failed: `сервис временно не доступен по пробуйте позднее`
- model reply not delivered: `сообщение не удалось доставить`

#### Scenario: Neural network failure path

- **WHEN** the neural network call fails or the agent returns no text
- **THEN** the stored record has processed-with-error status
- **AND** the chat receives `сервис временно не доступен по пробуйте позднее`

#### Scenario: Reply delivery failure path

- **WHEN** the agent returned a reply and sending that reply to Telegram fails
- **THEN** the stored record has processed-with-error status
- **AND** the chat receives `сообщение не удалось доставить`

#### Scenario: Error notify send failure still persists

- **WHEN** the failed-processing path cannot deliver the user-facing error message
- **THEN** the error record is still registered in the unit of work and committed with that chunk

### Requirement: Successful records keep full user text

When processing succeeds, the stored record MUST keep the incoming user text and MUST include chat id, message id, Telegram update id, send date, and available sender first name, last name, and nickname.

#### Scenario: Success record stores user facts and update id

- **WHEN** a message is processed successfully
- **THEN** the record contains the original text, user facts from Telegram, chat id, message id, update id, and send date
- **AND** its status is processed successfully

### Requirement: Processing is invokable as a batch job

The system SHALL expose an invokable processing run so a operator or scheduler can poll Telegram, process the inbox, and persist outcomes without a webhook.

#### Scenario: Operator starts a processing run

- **WHEN** an operator invokes the processing job
- **THEN** the system retrieves pending messages and processes them according to the requirements above
