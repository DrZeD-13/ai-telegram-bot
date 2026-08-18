# layered-src-layout Specification

## Purpose

Задаёт каркас каталогов `src/` и привязку Symfony-конфигов (Doctrine, HTTP-роуты, DI) к слоям из `docs/architecture.md`, без прикладных фич.

## Requirements

### Requirement: Layered source tree under src

The application source tree MUST follow the layered layout: `Application`, `Domain/Entity`, `Infrastructure/Persistence`, `Presentation/Http/Controller`, and `Presentation/Console`. `Kernel.php` MUST remain at the root of `src/`. The skeleton directories `src/Controller`, `src/Entity`, and `src/Repository` MUST NOT exist. Empty layer directories MUST still be present in the repository so new code has a defined place.

#### Scenario: Developer inspects src layout

- **WHEN** a developer lists `src/`
- **THEN** the directories `Application`, `Domain/Entity`, `Infrastructure/Persistence`, `Presentation/Http/Controller`, and `Presentation/Console` exist
- **AND** `src/Kernel.php` exists
- **AND** `src/Controller`, `src/Entity`, and `src/Repository` do not exist

### Requirement: Doctrine maps domain entities

Doctrine ORM MUST map entities from `src/Domain/Entity` with namespace prefix `App\Domain\Entity`. It MUST NOT map `src/Entity` or `App\Entity`.

#### Scenario: Entity mapping path is domain

- **WHEN** a developer inspects Doctrine ORM mapping configuration
- **THEN** the mapping directory is `src/Domain/Entity`
- **AND** the mapping prefix is `App\Domain\Entity`

### Requirement: HTTP routes load presentation controllers

HTTP attribute routes MUST be loaded from `src/Presentation/Http/Controller`. They MUST NOT be loaded from `src/Controller`.

#### Scenario: Router resource points at presentation

- **WHEN** a developer inspects the application route configuration
- **THEN** controller routes are imported from `Presentation/Http/Controller`

### Requirement: DI scans layers without treating entities as services

The service container MUST autowire classes under the layered `src/` tree. Domain entities under `Domain/Entity` and `Kernel.php` MUST NOT be registered as application services.

#### Scenario: Container excludes entities and kernel

- **WHEN** a developer inspects service autoloading configuration
- **THEN** `src/` is the service resource
- **AND** `Domain/Entity` and `Kernel.php` are excluded from service registration

### Requirement: Scaffold contains no domain features

The scaffold MUST NOT add Telegram bot code, Doctrine entity classes, HTTP controllers, or console commands. Layer directories MAY contain only placeholder files needed to keep the directories in version control.

#### Scenario: No application classes beyond Kernel

- **WHEN** a developer searches `src/` for PHP classes
- **THEN** the only application PHP class is `Kernel`
