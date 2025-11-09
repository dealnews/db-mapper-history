# Repository Guidelines

## Project Structure & Module Organization
- `src/` holds the DealNews DB history extensions; `AbstractMapper.php` is the core entry point for persistence history.
- `tests/` contains PHPUnit suites plus custom fixtures; `tests/bootstrap.php` wires autoloading and shared helpers.
- `schema/` offers reference SQL artifacts, while `vendor/` stores composer dependencies (do not edit manually).
- Root-level configs (`composer.json`, `phpunit.xml.dist`, `.php-cs-fixer.dist.php`) define tooling behavior—review before changing build workflows.

## Build, Test, and Development Commands
- `composer install` – install PHP dependencies and generate the autoloader.
- `composer test` – run parallel lint plus the entire PHPUnit suite (preferred pre-push check).
- `./vendor/bin/phpunit --filter <TestName>` – execute a focused test case during iterative development.
- `composer lint` / `composer fix` – lint or auto-fix PHP style issues via PHP-CS-Fixer; run lint before committing and fix if violations appear.

## Coding Style & Naming Conventions
- Follow PSR-12 with snake_case properties/variables and camelCase method names; declare class constants in SCREAMING_SNAKE_CASE.
- Prefer `protected` visibility for properties/methods unless public is required.
- Always provide complete PHPDoc blocks describing params, returns, and thrown exceptions when the context is unclear.
- Keep new files ASCII unless legacy content uses Unicode; avoid inline `var_dump`—use `_debug()` helper when debugging tests.

## Testing Guidelines
- PHPUnit 11 is bundled; integration-style tests should avoid mocking `Sarhan\Flatten\Flatten` and instead exercise real transformations.
- Name tests after the behavior under test (e.g., `testGenerateDiffDetectsNestedAdditions`) and colocate fixtures in the same file when lightweight.
- Aim to cover edge cases around CRUD history status (`create`, `update`, `delete`) and verify serialized payloads via `json_decode`.
- Enable coverage text reports via `phpunit.xml.dist`; keep coverage noise low by focusing on public/protected APIs.

## Commit & Pull Request Guidelines
- Use imperative, descriptive commit messages (“Add saveHistory regression tests”) and limit scope to a single logical change.
- PRs should summarize motivation, outline test evidence (composer test output), and reference relevant tickets/issues.
- Highlight behavioral risks, schema changes, or new dependencies in the PR body; attach screenshots only when modifying user-facing docs.

## Security & Configuration Tips
- Set `DN_INI_FILE` when tests rely on DB config; never commit real credentials.
- Avoid editing `vendor/` or generated caches—regenerate via composer instead. Clean sensitive data before attaching logs to issues.
