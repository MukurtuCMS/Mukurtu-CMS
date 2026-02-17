# Mukurtu Import Module - Detailed Research Report

## Overview

The `mukurtu_import` module provides CSV-based import functionality for **Mukurtu CMS**, a digital heritage management platform. It is built on top of Drupal's Migrate API (with `migrate_plus`, `migrate_tools`, and `migrate_source_csv` as dependencies) but wraps it in a user-friendly, multi-step wizard UI. The module allows users to upload CSV metadata files and binary/media files, configure source-to-target field mappings, and execute batch imports for content (nodes), media, communities, protocols, paragraphs, and multipage items.

**Key dependencies:** `drupal:migrate`, `migrate_plus`, `migrate_source_csv`, `migrate_tools`, `mukurtu_core`, `mukurtu_protocol`, `mukurtu_notifications`.

**Drupal compatibility:** `^10 || ^11`

---

## Architecture

### Core Concepts

1. **Import Strategy (`MukurtuImportStrategy`):** A config entity that stores a reusable mapping between CSV columns (sources) and Drupal entity fields (targets), along with CSV parsing settings. Users can save strategies as templates for repeated use.

2. **Field Process Plugin System (`MukurtuImportFieldProcess`):** A custom plugin type that translates field type information into Migrate API process pipeline configurations. Each plugin knows how to handle a specific Drupal field type (entity references, files, images, formatted text, etc.).

3. **Protocol-Aware Destination (`ProtocolAwareEntityContent`):** A custom Migrate destination plugin that replaces the default `EntityContentBase` for nodes, media, communities, and protocols. It enforces that imports run under the current user's permissions (no account switching) and handles Mukurtu-specific access control.

4. **Batch Execution (`ImportBatchExecutable`):** Extends `MigrateBatchExecutable` to support running multiple migration definitions in a single batch operation, with progress tracking and error message collection.

---

## File Structure

```
mukurtu_import/
├── config/
│   ├── install/                              # Views for import results display
│   │   ├── views.view.mukurtu_import_results_communities.yml
│   │   ├── views.view.mukurtu_import_results_content.yml
│   │   ├── views.view.mukurtu_import_results_cultural_protocols.yml
│   │   └── views.view.mukurtu_import_results_media.yml
│   └── schema/
│       └── mukurtu_import.schema.yml         # Config schema for import strategies
├── src/
│   ├── Attribute/
│   │   └── MukurtuImportFieldProcess.php     # PHP 8 attribute for field process plugins
│   ├── Controller/
│   │   └── BundleListController.php          # Lists entity types/bundles for format info
│   ├── Entity/
│   │   └── MukurtuImportStrategy.php         # Config entity: stores import mappings
│   ├── Form/
│   │   ├── ImportBaseForm.php                # Base form with shared import state/logic
│   │   ├── ImportFileUploadForm.php          # Step 1: Upload CSV + binary files
│   │   ├── ImportFileSummaryForm.php         # Step 2: Configure mappings per file
│   │   ├── CustomStrategyFromFileForm.php    # Step 2b: Customize field mappings
│   │   ├── ExecuteImportForm.php             # Step 3: Review and execute import
│   │   ├── ImportResultsForm.php             # Step 4: View import results
│   │   ├── ImportFieldDescriptionListForm.php # Format info + CSV template download
│   │   ├── MukurtuImportStrategyForm.php     # Admin form for strategy config entity
│   │   └── SettingsForm.php                  # Module settings (placeholder)
│   ├── Plugin/
│   │   ├── MukurtuImportFieldProcess/        # Field process plugins (custom plugin type)
│   │   │   ├── CulturalProtocols.php         # cultural_protocol field type
│   │   │   ├── DefaultProcess.php            # Fallback for all field types
│   │   │   ├── EntityReference.php           # entity_reference field type
│   │   │   ├── EntityReferenceRevisions.php  # entity_reference_revisions (paragraphs)
│   │   │   ├── File.php                      # file field type
│   │   │   ├── FormattedText.php             # text, text_long, text_with_summary
│   │   │   ├── Image.php                     # image field type (with subfield support)
│   │   │   ├── Link.php                      # link field type
│   │   │   └── ListString.php                # list_string field type
│   │   └── migrate/
│   │       ├── destination/
│   │       │   └── ProtocolAwareEntityContent.php  # Custom migrate destination
│   │       └── process/
│   │           ├── CurrentEntityRevision.php  # Looks up current revision for ERR fields
│   │           ├── FileItem.php               # Resolves file references by ID/filename
│   │           ├── ImageItem.php              # Resolves image file references
│   │           ├── LabelLookup.php            # Resolves list_string labels to keys
│   │           ├── MarkdownLink.php           # Parses [title](url) markdown links
│   │           ├── MukurtuEntityGenerate.php  # Entity lookup/generate (extends migrate_plus)
│   │           ├── MukurtuEntityLookup.php    # Entity lookup with access checks
│   │           └── UuidLookup.php             # Converts UUIDs to entity IDs
│   ├── FormattedTextProcessCallback.php       # Callback for formatted text processing
│   ├── ImportBatchExecutable.php              # Batch migration executor
│   ├── MukurtuImport.php                      # Simple data holder (metadata/binary files)
│   ├── MukurtuImportFieldProcessInterface.php # Interface for field process plugins
│   ├── MukurtuImportFieldProcessPluginBase.php # Base class for field process plugins
│   ├── MukurtuImportFieldProcessPluginManager.php # Plugin manager
│   ├── MukurtuImportStrategyInterface.php     # Interface for import strategy entity
│   └── MukurtuImportStrategyListBuilder.php   # Admin list builder for strategies
├── templates/
│   └── mukurtu-import-entity-bundle-list.html.twig  # Template for bundle list page
├── tests/src/Kernel/
│   ├── MukurtuImportTestBase.php              # Base test class with fixtures
│   ├── ImportBooleanTest.php
│   ├── ImportEntityLookupTest.php
│   ├── ImportEntityReferenceTest.php
│   ├── ImportFileReferenceTest.php
│   ├── ImportLinksTest.php
│   ├── ImportListStringTest.php
│   ├── ImportOriginalDateTest.php
│   ├── ImportParagraphReferenceTest.php
│   ├── ImportPlainTextTest.php
│   ├── ImportProtocolsTest.php
│   ├── ImportTaxonomyTermsTest.php
│   ├── ImportTimestampTest.php
│   └── ImportUsernameTest.php
├── mukurtu_import.info.yml
├── mukurtu_import.install
├── mukurtu_import.module
├── mukurtu_import.routing.yml
├── mukurtu_import.services.yml
├── mukurtu_import.permissions.yml
├── mukurtu_import.links.menu.yml
├── mukurtu_import.links.action.yml
└── mukurtu_import.features.yml
```

---

## Import Workflow (User-Facing)

The import is a **4-step wizard** implemented as separate routes/forms, with state persisted in Drupal's `PrivateTempStore` (keyed by `mukurtu_import`):

### Step 1: Upload Files (`/admin/import` — `ImportFileUploadForm`)

- Users upload **metadata files** (CSV only, validated by extension and header parsing).
- Users upload **binary/media files** (extensions dynamically discovered from all configured media bundles).
- Files are stored in the private filesystem under unique import session paths:
  - Metadata: `private://{import_id}/metadata/`
  - Binary: `private://{import_id}/files/`
- The `import_id` is a UUID generated per import session and stored in the tempstore.
- Permanent binary files (already in use by other entities) have their removal checkbox disabled.
- A "Reset" button clears all tempstore state.

### Step 2: Configure Mappings (`/admin/import/files` — `ImportFileSummaryForm`)

- Displays a **draggable table** of uploaded CSV files, each with:
  - A dropdown to select an existing **Import Configuration Template** (saved `MukurtuImportStrategy` entities owned by the current user) or "Custom Settings".
  - A "Customize Settings" button to go to the detailed mapping form.
  - A weight column for ordering import execution.
  - Summary messages showing target entity type/bundle and mapped field count.
- AJAX callbacks update the UI when the mapping selection changes.
- Validation ensures all files have at least one mapped field before proceeding.

### Step 2b: Customize Mappings (`/admin/import/files/mapping/add/{file}` — `CustomStrategyFromFileForm`)

- Lets users define the **target entity type** (node, media, community, protocol, paragraph, multipage_item) via radio buttons.
- Lets users select the **target bundle** (filtered by user's create access).
- Builds a **mapping table** with one row per CSV column header:
  - Column name (from CSV header) as source.
  - A dropdown of available target fields (including subfield properties like `image/alt` or `cultural_protocols/sharing_setting`).
  - **Auto-mapping** logic attempts to match CSV headers to field labels or names (case-insensitive).
- **File Settings** (collapsible):
  - CSV delimiter (default `,`), enclosure (default `"`), escape character (default `\`).
  - Multi-value delimiter (default `;`) for fields with cardinality > 1.
  - Default text format for formatted text fields (default `basic_html`).
- Option to **save the configuration as a reusable template** (stored as a `MukurtuImportStrategy` config entity).
- Validates that no target field is mapped more than once (except "Ignore").
- AJAX-driven: changing entity type or bundle refreshes the available target fields and auto-mappings.

### Step 3: Review & Execute (`/admin/import/run` — `ExecuteImportForm`)

- Displays a read-only summary table: filename, configuration label, destination type/bundle.
- Warning: "Once you begin the import you cannot stop it. There is no way to rollback."
- On submit:
  1. Migration definitions are built for each file via `MukurtuImportStrategy::toDefinition()`.
  2. A `mukurtu_import_message` is attached to each definition (used as revision log message).
  3. A bootstrap `ImportBatchExecutable` runs all definitions via `batchImportMultiple()`.
- Files are processed in weight order.

### Step 4: Results (`/admin/import/results` — `ImportResultsForm`)

- Shows success/failure messages from the batch operation.
- Per-file error messages include the source ID that caused the failure.
- Embeds four **Views** to display imported entities:
  - Communities, Cultural Protocols, Media, Content.
  - Filtered by the import's revision log message.
- "Return to Uploaded Files" on failure; "Start a new import" on success.

---

## Import Strategy Config Entity (`MukurtuImportStrategy`)

**Entity type ID:** `mukurtu_import_strategy`
**Type:** Config entity
**Config prefix:** `mukurtu_import.mukurtu_import_strategy`

### Properties

| Property | Type | Description |
|---|---|---|
| `id` | string | UUID (auto-generated on save) |
| `label` | string | User-facing label |
| `uid` | int | Owner user ID |
| `description` | string | Description |
| `target_entity_type_id` | string | Target entity type (e.g., `node`, `media`) |
| `target_bundle` | string | Target bundle (e.g., `article`) |
| `mapping` | array | Array of `{source, target}` pairs |
| `default_format` | string | Default text format for formatted text fields |

### Key Methods

- **`applies(FileInterface $file)`**: Checks if a strategy's source columns all exist in the given CSV file's headers.
- **`toDefinition(FileInterface $file)`**: Generates a complete Migrate API definition array, including source (CSV plugin), process pipeline (from field process plugins), and destination configuration.
- **`getProcess()`**: Core method that builds the migrate process pipeline. For each mapping entry, it:
  1. Looks up the field definition for the target.
  2. Gets the appropriate `MukurtuImportFieldProcess` plugin.
  3. Calls `getProcess()` on that plugin to get the migrate process configuration.
  4. Passes context (multivalue delimiter, upload location, default format, subfield info).
- **`getOverwriteProperties()`**: Returns writable field names from the mapping (used for `overwrite_properties` in the destination config to enable updating existing entities).
- **`mappedFieldsCount(FileInterface $file)`**: Compares CSV headers against mapped sources.
- **`getMappedTarget(string $source)`**: Finds which target a given source column is mapped to.

### Migration Definition Structure

The `toDefinition()` method generates:

```php
[
  'id' => '{uid}__{fid}__{entity_type}__{bundle}',
  'source' => [
    'plugin' => 'csv',
    'path' => $file->getFileUri(),
    'ids' => [...],  // ID key, UUID key, or _record_number
    'delimiter' => ',',
    'enclosure' => '"',
    'escape' => '\\',
    'track_changes' => TRUE,
    'create_record_number' => TRUE,
    'record_number_field' => '_record_number',
  ],
  'process' => [...],  // Built from field process plugins
  'destination' => [
    'plugin' => 'entity:{entity_type}',
    'default_bundle' => $bundle,
    'overwrite_properties' => [...],
    'validate' => TRUE,
  ],
]
```

### ID Resolution Priority

1. Entity ID (e.g., `nid`) if mapped.
2. UUID if mapped.
3. Fallback to `_record_number` (auto-generated by CSV source plugin) — this means each row creates a new entity.

---

## Custom Plugin System: MukurtuImportFieldProcess

### Overview

A custom Drupal plugin type that provides a strategy pattern for converting different Drupal field types into Migrate API process configurations. Uses PHP 8 attributes for discovery.

### Plugin Manager (`MukurtuImportFieldProcessPluginManager`)

- Namespace: `Plugin/MukurtuImportFieldProcess`
- Attribute: `#[MukurtuImportFieldProcess]`
- `getInstance()` method selects the best plugin for a field definition by:
  1. Filtering plugins whose `field_types` include the field's type.
  2. Checking `isApplicable()` on each candidate.
  3. Selecting the one with the lowest weight.
  4. Falling back to the `default` plugin.

### Plugin Attribute Properties

| Property | Type | Description |
|---|---|---|
| `id` | string | Plugin ID |
| `label` | TranslatableMarkup | Human-readable label |
| `description` | TranslatableMarkup | Description |
| `field_types` | array | Drupal field types this plugin handles |
| `weight` | int | Priority (lower = preferred) |
| `properties` | array | Subfield properties this plugin supports (e.g., `['target_id', 'alt']` for image) |

### Plugins

#### `DefaultProcess` (id: `default`, field_types: `['*']`)
- Fallback for any field type not handled by a specific plugin.
- For single-value: passes source value directly.
- For multi-value: uses `explode` process plugin with the configured delimiter.

#### `EntityReference` (id: `entity_reference`, field_types: `['entity_reference']`)
- Handles references to: communities, media, nodes, protocols, taxonomy terms, users.
- Process pipeline:
  1. `explode` (if multi-value).
  2. `uuid_lookup` — converts UUIDs to IDs.
  3. Entity lookup/generate (varies by target type):
     - **Taxonomy terms:** `mukurtu_entity_generate` or `mukurtu_entity_lookup` by `name`, with `auto_create` support.
     - **Community/media/node/protocol:** `mukurtu_entity_lookup` by label.
     - **User:** `mukurtu_entity_lookup` by `name`.
     - **Default:** `mukurtu_entity_lookup` by `uuid`.

#### `EntityReferenceRevisions` (id: `entity_reference_revisions`, field_types: `['entity_reference_revisions']`)
- Extends `EntityReference` and adds `current_entity_revision` process plugin at the end.
- Handles paragraph references (and other ERR field types).

#### `File` (id: `file`, field_types: `['file']`)
- Pipeline: `explode` → `uuid_lookup` → `mukurtu_fileitem`.
- Files resolved by ID, UUID, or filename within the import's upload directory.

#### `Image` (id: `image`, field_types: `['image']`, properties: `['target_id', 'alt']`)
- Supports **subfield mapping**: `target_id` and `alt` can be mapped to separate CSV columns.
- For `target_id`: uses `mukurtu_imageitem` process plugin (same logic as `FileItem`).
- For `alt`: uses `get` process plugin (passes through directly).

#### `FormattedText` (id: `formatted_text`, field_types: `['text', 'text_long', 'text_with_summary']`)
- Uses `callback` process plugin with `FormattedTextProcessCallback`.
- The callback wraps the value in `['value' => $value, 'format' => $default_format]`.

#### `Link` (id: `link`, field_types: `['link']`)
- Uses `markdown_link` process plugin.
- Expected input format: `[Title](https://url.com)`.
- Parsed to `['title' => ..., 'uri' => ...]`.

#### `ListString` (id: `list_string`, field_types: `['list_string']`)
- Uses `label_lookup` process plugin.
- Accepts either the machine key or the human-readable label (case-insensitive).

#### `CulturalProtocols` (id: `cultural_protocol`, field_types: `['cultural_protocol']`, properties: `['protocols', 'sharing_setting']`)
- A Mukurtu-specific compound field with two subfields:
  - **`protocols`**: Multi-value reference to protocol entities. Resolved by ID, UUID, or name.
  - **`sharing_setting`**: Single value, trimmed and lowercased. Expected: `any` or `all`.

---

## Migrate Process Plugins

### `uuid_lookup` (id: `uuid_lookup`)
- Converts a UUID string to an entity ID using `loadByProperties(['uuid' => $value])`.
- If the value doesn't match the UUID v4 regex, it passes through unchanged.
- This allows mixed input (IDs and UUIDs in the same column).

### `mukurtu_entity_lookup` (id: `mukurtu_entity_lookup`)
- Extends `migrate_plus` `EntityLookup`.
- Key differences from the parent:
  - **Always uses `accessCheck(TRUE)`** — respects entity access.
  - **Rejects ambiguous lookups**: throws `MigrateException` if more than one entity matches a label.
  - First checks if the value is a valid numeric entity ID before doing a label lookup.

### `mukurtu_entity_generate` (id: `mukurtu_entity_generate`)
- Extends `migrate_plus` `EntityGenerate`.
- Same ID-first-then-label lookup logic as `MukurtuEntityLookup`.
- Used for taxonomy terms with `auto_create` enabled — creates new terms if they don't exist.

### `mukurtu_fileitem` (id: `mukurtu_fileitem`)
- Resolves a file reference value to a file entity ID.
- Resolution order:
  1. If numeric → load by file ID.
  2. If string → query by filename within the import's upload location (`STARTS_WITH` condition on URI).
- Returns `NULL` if not found.

### `mukurtu_imageitem` (id: `mukurtu_imageitem`)
- Identical logic to `mukurtu_fileitem` but returns `[]` (empty array) on failure instead of `NULL`.

### `label_lookup` (id: `label_lookup`)
- For `list_string` fields: looks up the machine name key from a human-readable label.
- Case-insensitive label matching using `mb_strtolower`.
- If the value already matches a valid key, returns it unchanged.

### `markdown_link` (id: `markdown_link`)
- Parses `[title](url)` markdown link format.
- Returns `['title' => ..., 'uri' => ...]` for Drupal's link field format.
- If no match, returns the raw value unchanged.

### `current_entity_revision` (id: `current_entity_revision`)
- Takes an entity ID (or `['target_id' => ...]` array) and looks up the current revision.
- Returns `['target_id' => ..., 'target_revision_id' => ...]`.
- Used for `entity_reference_revisions` fields (paragraphs) which require both target_id and revision_id.

---

## Protocol-Aware Destination (`ProtocolAwareEntityContent`)

### Purpose

Replaces the default `EntityContentBase` destination for `entity:node`, `entity:media`, `entity:community`, and `entity:protocol` via `hook_migrate_destination_info_alter()`.

### Key Behavioral Differences from `EntityContentBase`

1. **No Account Switching**: `EntityContentBase` switches to the content owner's account for access checks. `ProtocolAwareEntityContent` deliberately does NOT do this — the import runs under the current user's account, meaning the importer must have permission to create/edit the target entities. This is intentional for Mukurtu's protocol-based access control.

2. **UUID-based Entity Lookup**: `getEntityId()` first checks for a mapped entity ID, then falls back to looking up by UUID. This allows imports to reference existing entities by UUID.

3. **Validation**: Overrides `validateEntity()` to:
   - Skip account switching.
   - Add alt text validation constraint for image media entities (`ImageAltRequired`).

4. **Media `prepareSave()`**: Before validation, calls `prepareSave()` on media entities to auto-populate the `name` field from the source file's filename.

5. **Revision Logging**: On save, creates a new revision with a log message containing the import ID and the importing user's name. Sets the revision user to the current user.

---

## State Management

All import session state is stored in Drupal's `PrivateTempStore` under the `mukurtu_import` collection:

| Key | Type | Description |
|---|---|---|
| `import_id` | string (UUID) | Unique identifier for the import session |
| `import_config` | array | Per-file import strategy objects (keyed by fid) |
| `metadata_file_weights` | array | File ordering (keyed by fid, values are weights) |
| `batch_results_success` | bool | Whether the last batch completed successfully |
| `batch_results_messages` | array | Error messages from the last batch, with fid association |

The `ImportBaseForm` base class manages this state and provides helpers for file/config management.

---

## Auto-Mapping Logic

When building the mapping table in `CustomStrategyFromFileForm`, the module attempts automatic field mapping via `getAutoMappedTarget()`:

1. **Existing config mapping**: If the current strategy already has a valid mapping for this source column, use it.
2. **Plugin property labels**: Check if any field process plugin's supported properties have a label matching the CSV header (case-insensitive).
3. **Field label match**: Case-insensitive match against field labels. Prefers bundle-specific fields over base fields when there are multiple matches.
4. **Field name match**: Case-insensitive exact match against field machine names.
5. **Fallback**: `-1` (Ignore).

---

## Import Format Information Pages

### Bundle List (`/admin/import/format` — `BundleListController`)

Lists all importable entity types (node, media, community, protocol, paragraph, file, multipage_item) with links to their per-bundle field description pages.

### Field Description List (`/admin/import/format/{entity_type}/{bundle}` — `ImportFieldDescriptionListForm`)

- Displays a **tableselect** of all importable fields for a given entity type/bundle.
- Each row shows: field label, field description, import format description (from the field process plugin's `getFormatDescription()`).
- Users can select fields and **download a CSV template** with those field labels as headers.

---

## Batch Execution (`ImportBatchExecutable`)

### `batchImportMultiple(array $migration_definitions)`

- Creates one batch operation per migration definition.
- Each operation calls `batchProcessImportDefinition()`.

### `batchProcessImportDefinition()`

- Creates a stub migration from the definition.
- Creates a new `ImportBatchExecutable` instance per iteration.
- Tracks: `@numitems`, `@created`, `@updated`, `@failures`, `@ignored`.
- Collects migration messages (errors/warnings) via `getIdMap()->getMessages()`.
- Progress calculated as `processed / total`.

### `batchFinishedImport()`

- Stores results in the tempstore.
- Parses migration IDs to extract file IDs (format: `{uid}__{fid}__{entity_type}__{bundle}`).
- Associates error messages with the file that caused them.
- Cleans up process pipeline error messages for readability by stripping the migration plugin ID prefix.

---

## Testing

### Base Test Class (`MukurtuImportTestBase`)

- Extends `MigrateTestBase`.
- Sets up: user entity schema, file schema, taxonomy, OG (Organic Groups) for communities/protocols, node types, roles, and permissions.
- Creates a test user, community, and protocol with proper OG memberships.
- Provides `createCsvFile(array $data)` — creates a temporary CSV file entity from array data.
- Provides `importCsvFile(FileInterface $file, array $mapping, ...)` — runs a complete import and returns the migration result code.

### Test Coverage

14 kernel test classes covering:

| Test | What's Tested |
|---|---|
| `ImportPlainTextTest` | Title/plain text field import |
| `ImportBooleanTest` | Boolean field import |
| `ImportEntityLookupTest` | Entity reference by name/ID |
| `ImportEntityReferenceTest` | Entity reference fields |
| `ImportFileReferenceTest` | File field import by ID and UUID |
| `ImportLinksTest` | Link field (markdown format) |
| `ImportListStringTest` | List string (select/options) import |
| `ImportOriginalDateTest` | Custom date format |
| `ImportParagraphReferenceTest` | Entity reference revisions (paragraphs) |
| `ImportProtocolsTest` | Cultural protocol fields (by ID, UUID, name; sharing setting; protocol string format) |
| `ImportTaxonomyTermsTest` | Taxonomy term references |
| `ImportTimestampTest` | Timestamp fields |
| `ImportUsernameTest` | User reference by username |

---

## Permissions

| Permission | Description |
|---|---|
| `administer mukurtu_import_strategy` | Manage import configuration templates |
| `access mukurtu import` | Access the import wizard UI |
| `administer mukurtu_import configuration` | Access module settings form |

---

## Routes

| Route | Path | Purpose |
|---|---|---|
| `mukurtu_import.file_upload` | `/admin/import` | Step 1: File upload |
| `mukurtu_import.import_files` | `/admin/import/files` | Step 2: File configuration |
| `mukurtu_import.custom_strategy_from_file_form` | `/admin/import/files/mapping/add/{file}` | Step 2b: Custom mapping |
| `mukurtu_import.execute_import` | `/admin/import/run` | Step 3: Review & execute |
| `mukurtu_import.import_results` | `/admin/import/results` | Step 4: Results |
| `mukurtu_import.bundles_list` | `/admin/import/format` | Format info: entity type list |
| `mukurtu_import.fields_list` | `/admin/import/format/{entity_type}/{bundle}` | Format info: field list |
| `mukurtu_import.settings_form` | `/admin/config/system/mukurtu-import` | Module settings |
| `entity.mukurtu_import_strategy.*` | `/admin/structure/mukurtu-import-strategy/*` | Strategy CRUD |

---

## Notable Design Decisions & Observations

### Deliberate Protocol Enforcement
The most architecturally significant decision is the `ProtocolAwareEntityContent` destination plugin. Unlike standard Drupal migrations, the import does NOT switch to the entity owner's account. This ensures Mukurtu's cultural protocol access controls are respected during import — if a user doesn't have permission to create content under a specific protocol, the import will fail for that row.

### Subfield Mapping
The plugin system supports mapping to individual subfields of compound field types. This is implemented via the `/` delimiter in target field names (e.g., `field_cultural_protocols/sharing_setting`, `field_media_image/alt`). The `properties` attribute on plugins declares which subfields are available.

### Entity Resolution Flexibility
Entity references can be resolved by multiple identifiers: entity ID (numeric), UUID, or label/name. The `uuid_lookup` process plugin runs first to convert UUIDs, then the entity lookup plugin falls back to label matching if the value isn't an ID. This allows users to mix reference formats in their CSV data.

### Settings Form is a Placeholder
The `SettingsForm` class is essentially scaffolded boilerplate with a single "example" field that validates to the literal string "example". It's not functional.

### Install Schema is Scaffolding
The `mukurtu_import_example` table defined in `hook_schema()` and the `hook_requirements()` random value check are both leftover scaffolding from module generation and are not used by the actual import functionality.

### No Rollback Support
The module explicitly warns that imports cannot be rolled back. While the Migrate API supports rollbacks, this module uses `createStubMigration()` which creates transient migrations that don't persist their ID maps.

### File Upload Restrictions
Binary file upload validators are dynamically generated from all configured media type field definitions, ensuring the import accepts the same file types as regular media uploads.

### Revision Tracking
Imported entities get a specific revision log message: "Imported by {username} (Import ID: {uuid})". This is used by the results Views to filter and display only entities created/updated in the current import session.

### Multi-value Delimiter
The default multi-value delimiter is `;` (semicolon). This separates multiple values within a single CSV cell for fields with cardinality > 1 (e.g., `tag1;tag2;tag3` for a multi-value taxonomy reference).

### Error Handling in Batch
Error messages from the migrate process pipeline are cleaned up by stripping the migration plugin ID prefix (`{plugin_id}:{source}:{destination}:{message}` → just `{message}`). Messages are tagged with the file ID that caused them for display in the results form.
