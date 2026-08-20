## Purpose

Хранит входящие сообщения Telegram и исход их обработки: кто написал, из какого чата, текст, когда отправили, статус и текст ошибки.

## ADDED Requirements

### Requirement: Persist incoming Telegram message facts

The system SHALL persist a record for an incoming Telegram message with: sender first name, last name, and nickname; chat identifier; message identifier; message text; and the date the message was sent in Telegram. First name, last name, nickname, and message text MAY be empty when Telegram did not provide them. Chat identifier and message identifier MUST be present.

#### Scenario: Record stores requested fields

- **WHEN** an incoming Telegram message is stored
- **THEN** the record contains chat id, message id, send date, and the available sender first name, last name, nickname, and message text

#### Scenario: Missing optional user and text fields

- **WHEN** Telegram omits last name, nickname, or text
- **THEN** the record is still stored and those fields are empty rather than rejected

### Requirement: Processing status is one of three values

The system SHALL represent processing status as exactly three values: not processed, processed successfully, and processed with error. A newly stored record MUST start as not processed.

#### Scenario: New record is not processed

- **WHEN** a message record is created
- **THEN** its status is not processed
- **AND** error text is empty

#### Scenario: Successful processing

- **WHEN** processing of a stored message completes without error
- **THEN** the record status is processed successfully
- **AND** error text is empty

#### Scenario: Failed processing

- **WHEN** processing of a stored message fails
- **THEN** the record status is processed with error
- **AND** the record stores the error text

### Requirement: Record timestamps

The system SHALL store the date and time the record was created and the date and time it was last changed. Created-at MUST be set on insert. Updated-at MUST change when the record is modified after insert.

#### Scenario: Creation stamps both dates

- **WHEN** a message record is first stored
- **THEN** created-at and updated-at are set

#### Scenario: Update refreshes updated-at

- **WHEN** status or error text of an existing record is changed
- **THEN** updated-at is later than created-at
- **AND** created-at is unchanged

### Requirement: Unique message per chat

The system MUST NOT store two records with the same pair of chat identifier and message identifier.

#### Scenario: Duplicate chat and message id is rejected

- **WHEN** a record already exists for a chat id and message id
- **AND** another store is attempted with the same chat id and message id
- **THEN** the system does not create a second record

### Requirement: Schema comments describe purpose

The persisted table MUST have a database comment that states the table purpose. Every column MUST have a database comment that states that column's purpose, except the surrogate identifier and the record created-at and updated-at timestamps, which MAY omit comments.

#### Scenario: Table and non-obvious columns are commented

- **WHEN** a developer inspects the table definition in the database
- **THEN** the table has a comment describing that it stores incoming Telegram messages and their processing outcome
- **AND** columns other than id, created-at, and updated-at each have a comment describing their purpose

#### Scenario: Obvious timestamp and id columns may omit comments

- **WHEN** a developer inspects the id, created-at, and updated-at columns
- **THEN** those columns MAY have no database comment
