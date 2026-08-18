## 1. Layer directories

- [x] 1.1 Create `src/Application`, `src/Domain/Entity`, `src/Infrastructure/Persistence/Repository`, `src/Presentation/Http/Controller`, `src/Presentation/Console` with an empty `.gitignore` in each (same placeholder as current skeleton dirs)
- [x] 1.2 Delete `src/Controller`, `src/Entity`, and `src/Repository`; keep `src/Kernel.php`

## 2. Symfony config

- [x] 2.1 In `config/packages/doctrine.yaml` set ORM mapping `dir` to `%kernel.project_dir%/src/Domain/Entity` and `prefix` to `App\Domain\Entity`; do not map `App\Entity`
- [x] 2.2 In `config/routes.yaml` replace `routing.controllers` with an attribute import of `../src/Presentation/Http/Controller/` (namespace `App\Presentation\Http\Controller` if required)
- [x] 2.3 In `config/services.yaml` keep `App\` resource `../src/` and exclude `../src/Domain/Entity/` and `../src/Kernel.php`

## 3. Verify scaffold

- [x] 3.1 Confirm `src/` has no PHP classes other than `Kernel` (no entities, controllers, commands, or bot code)
- [x] 3.2 Run `bin/console list` (inside the PHP container if that is how the project is run) so the app boots with the new mapping and routes
