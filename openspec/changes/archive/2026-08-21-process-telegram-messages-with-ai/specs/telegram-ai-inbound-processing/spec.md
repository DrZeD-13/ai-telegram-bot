## Purpose

Обрабатывает входящие текстовые сообщения Telegram: забирает новые апдейты по курсору из хранилища, отвечает нейросетью и фиксирует успех или ошибку пачками.

## ADDED Requirements

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

### Requirement: Reject user text longer than 1024 characters

The system MUST treat incoming text longer than 1024 characters as a validation failure and MUST follow the failed-processing path for that message. Text of 1024 characters or fewer MUST continue to neural network processing.

#### Scenario: Text over the limit is a validation failure

- **WHEN** an incoming message text length is greater than 1024 characters
- **THEN** the system does not call the neural network
- **AND** it follows the failed-processing path with the validation error message to the user

#### Scenario: Text within the limit is accepted

- **WHEN** an incoming message text length is 1024 characters or fewer and is non-empty
- **THEN** the system sends that text to the neural network

### Requirement: Ask the neural network with a length instruction

When validation succeeds, the system SHALL send the user text to the neural network through the existing neural network port. The request text MUST append the instruction that the answer must not exceed 1024 characters. The neural network reply MUST then be sent back to the same Telegram chat.

#### Scenario: Successful answer is delivered

- **WHEN** the neural network returns a text reply and Telegram accepts the send
- **THEN** the user receives that reply in the originating chat
- **AND** the stored record status is processed successfully
- **AND** error text on the record is empty

#### Scenario: Prompt includes the length instruction

- **WHEN** the system calls the neural network for a valid incoming text
- **THEN** the text sent to the model ends with the instruction to keep the answer no longer than 1024 characters

### Requirement: Failed processing stores an error record and notifies the user

When processing fails for validation, neural network unavailability, or failure to deliver the model reply, the system SHALL create a processed-message record filled from the incoming user data, MUST truncate the stored user text to at most 1024 characters, MUST set status to processed with error, MUST store the corresponding error text, and MUST send the user-facing error message to that chat. Persistence of the error record MUST still happen if sending the user-facing error message fails.

User-facing chat messages MUST be:

- validation (text longer than 1024 characters): `запрос слишком длиный сделайте не более 1024 символов`
- neural network unavailable or failed: `сервис временно не доступен по пробуйте позднее`
- model reply not delivered: `сообщение не удалось доставить`

#### Scenario: Validation failure path

- **WHEN** incoming text is longer than 1024 characters
- **THEN** the stored record has processed-with-error status
- **AND** stored text is truncated to 1024 characters
- **AND** the chat receives `запрос слишком длиный сделайте не более 1024 символов`

#### Scenario: Neural network failure path

- **WHEN** the neural network call fails or returns no text
- **THEN** the stored record has processed-with-error status
- **AND** stored user text is truncated to at most 1024 characters
- **AND** the chat receives `сервис временно не доступен по пробуйте позднее`

#### Scenario: Reply delivery failure path

- **WHEN** the neural network returned a reply and sending that reply to Telegram fails
- **THEN** the stored record has processed-with-error status
- **AND** the chat receives `сообщение не удалось доставить`

#### Scenario: Error notify send failure still persists

- **WHEN** the failed-processing path cannot deliver the user-facing error message
- **THEN** the error record is still registered in the unit of work and committed with that chunk

### Requirement: Successful records keep full accepted user text

When processing succeeds, the stored record MUST keep the incoming user text that passed validation (no truncation) and MUST include chat id, message id, Telegram update id, send date, and available sender first name, last name, and nickname.

#### Scenario: Success record stores user facts and update id

- **WHEN** a message is processed successfully
- **THEN** the record contains the original accepted text, user facts from Telegram, chat id, message id, update id, and send date
- **AND** its status is processed successfully

### Requirement: Processing is invokable as a batch job

The system SHALL expose an invokable processing run so a operator or scheduler can poll Telegram, process the inbox, and persist outcomes without a webhook.

#### Scenario: Operator starts a processing run

- **WHEN** an operator invokes the processing job
- **THEN** the system retrieves pending messages and processes them according to the requirements above
