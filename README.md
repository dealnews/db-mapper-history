# AbstractMapper Overview

`DealNews\DB\History\AbstractMapper` extends the base DealNews data mapper to provide transparent revision-history tracking for any mapped entity. It wraps the standard CRUD lifecycle with logic that snapshots every create, update, and delete into a dedicated `revision_history` table so consumers can audit changes later.

## Core Responsibilities
- **Persistence delegation** – inherits regular mapper behavior (validation, mapping arrays to objects, saving via `CRUD`) from `\DealNews\DB\AbstractMapper`.
- **History orchestration** – injects a secondary `CRUD` connection (`$history_crud`) pointed at the history database defined by `REVISION_HISTORY_DATABASE_NAME`.
- **Change detection** – computes diffs between the previous and new object states via `generateDiff()` and stores structured `added`/`removed` payloads as JSON.
- **User attribution** – records the actor responsible for a change through `getUser()`, which defaults to `"unknown"` but can be overridden per-project.

## Lifecycle Hooks
1. **`save()`**  
   - Loads the current record (if any), calls the parent save, then delegates to `saveHistory()` to record either a `create` or `update` event.
2. **`delete()`**  
   - Loads the record, deletes it via the parent mapper, and stores a `delete` event containing the removed payload.
3. **`saveHistory()`**  
   - Determines the operation type, builds the JSON structure, and writes to the `REVISION_HISTORY_TABLE_NAME` table with fields `object_id`, `object_type`, `object`, `status`, and `user`.

## Extending the Class
When creating a concrete mapper:
1. Define the base mapper constants (`MAPPED_CLASS`, `PRIMARY_KEY`, `MAPPING`, etc.).
2. Set the revision-history constants:
   ```php
   protected const REVISION_HISTORY_DATABASE_NAME = 'mapper_history';
   protected const REVISION_HISTORY_TABLE_NAME = 'revision_history';
   protected const REVISION_HISTORY_NAME = 'product';
   ```
   - `REVISION_HISTORY_DATABASE_NAME` – database/connection alias for history writes.
   - `REVISION_HISTORY_TABLE_NAME` – table name (defaults to `revision_history`).
   - `REVISION_HISTORY_NAME` – friendly label stored in `object_type` (falls back to `MAPPED_CLASS` when empty).
3. Optionally override `getUser()` to integrate with your authentication/session layer.

## Diff Generation Details
- Uses `Sarhan\Flatten\Flatten` to flatten nested arrays before comparing values.
- `generateDiff()` returns:
  ```php
  [
      'added'   => [...], // fields present or changed in the new object
      'removed' => [...], // fields removed or changed from the old object
  ]
  ```
- The structure is later `json_encode`d and stored, enabling downstream tools to display rich change logs.

## Schema Examples
- The `schema/mysql.sql` file contains a ready-to-use `revision_history` table definition, including recommended indexes and partitioning notes for pruning old data.
- The `schema/postgres.sql` file mirrors the table structure with PostgreSQL-specific types and default handling so you can deploy history storage without manual conversion.
- The `schema/sqlite.sql` file offers a lightweight definition for local development or embedded use cases where SQLite backs the mapper history.
- Point `REVISION_HISTORY_DATABASE_NAME`/`REVISION_HISTORY_TABLE_NAME` at an instance created from any of these schemas (or adapt them) to get consistent storage across services.

## Usage Tips
- Always provide the active user context before calling `save()`/`delete()` if auditing requires attribution (override `getUser()`).
- Handle `\RuntimeException` thrown by `saveHistory()` when neither the old nor new object supplies a primary key—this usually signals a failed persistence attempt.
- Use `composer test` to run the PHPUnit suite after introducing new mappers or adjusting history behavior to ensure diffs serialize as expected.
