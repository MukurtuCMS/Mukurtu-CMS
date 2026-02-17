# Plan: Cross-Migration Entity Reference Lookup

## Problem Statement

When a user uploads multiple CSVs in a single import session — e.g., `Media.csv` (creating Image media entities) and `Digital Heritage.csv` (creating nodes that reference those media) — there is currently no mechanism to reference entities created by one CSV from another CSV in the same import.

The user cannot know ahead of time the ID or UUID that a newly-created media entity will receive. They should be able to write a human-readable value like `"My Photo"` in the `field_media_assets` column of `Digital Heritage.csv`, and the system should resolve it to the media entity created from `Media.csv` during the same import session.

### Why The Current System Almost Works (But Not Quite)

The existing `EntityReference` field process plugin (`src/Plugin/MukurtuImportFieldProcess/EntityReference.php`) already builds a process pipeline that includes `mukurtu_entity_lookup`, which looks up entities by label. Since migrations run sequentially (in weight order), the media entities *do* exist in the database by the time the Digital Heritage migration runs.

However, `mukurtu_entity_lookup` has a critical limitation: it **throws an exception if the lookup is ambiguous** (multiple entities share the same label). When importing, a user might have pre-existing media entities with the same name, or the lookup column might not match the entity's name field exactly. The Migrate API's `migration_lookup` pattern is the standard solution — it uses deterministic source-to-destination ID maps rather than entity label matching.

### Why Core `migration_lookup` Won't Work Directly

Drupal core's `migration_lookup` process plugin (`core/modules/migrate/src/Plugin/migrate/process/MigrationLookup.php`) uses the `MigrateLookup` service, which calls `$this->migrationPluginManager->createInstances($migration_id)` to load migrations. The mukurtu_import module creates transient migrations via `createStubMigration()` — these are **not registered** with the plugin manager and cannot be discovered by `migration_lookup`.

### Artifacts Left Behind by `createStubMigration()`

`createStubMigration()` (in `MigrationPluginManager`) simply instantiates a `Migration` plugin object from a raw array definition — it does **not** register the migration with the plugin manager. The object lives only in memory and cannot be discovered by other code (e.g., `migration_lookup`, `createInstances()`).

However, running a stub migration **does** create persistent database artifacts. The `Sql` ID map plugin (which is the default) lazily creates two tables the first time the ID map is accessed:

1. **`migrate_map_{migration_id}`** — maps source IDs to destination entity IDs (used for rollback, duplicate detection, and cross-migration lookups).
2. **`migrate_message_{migration_id}`** — stores error/warning messages from the migration run.

Since mukurtu_import's migration IDs are deterministic (e.g., `{uid}__{fid}__{entity_type}__{bundle}`), these tables accumulate in the database after each import. **Currently, mukurtu_import never cleans them up.** There is no call to `$idMap->destroy()` (which drops both tables) anywhere in the module.

For this plan, the ID map tables are actually essential — `ImportMigrationLookup` queries `migrate_map_*` tables directly. The tables must persist at least until all migrations in the batch complete. After that, they should be cleaned up.

**Recommended cleanup approach:** After batch completion in `ImportBatchExecutable::batchFinishedImport()`, iterate through the completed migration definitions and call `destroy()` on each ID map. This is addressed in Step 5 below.

### Alternatives to `createStubMigration()`

There are several alternatives worth noting, though none are clearly better for this module's use case:

1. **`hook_migration_plugins_alter(&$definitions)`** — Injects dynamic definitions into the discovery system, making migrations fully registered. However, this hook fires during plugin cache rebuilds and doesn't have access to per-request state (file paths, user session data), making it impractical for user-initiated imports.

2. **YAML migration templates + `createInstances()`** — Ship parameterized YAML files in `migrations/` and pass file-specific config at runtime. Makes migrations registered but is inflexible for the fully dynamic field mapping this module supports.

3. **Custom in-memory ID map plugin** — Replace the `Sql` ID map with an array-backed implementation to avoid DB artifacts entirely. Loses duplicate detection across batch boundaries and wouldn't support the cross-migration lookup approach in this plan (which queries the DB table directly).

**Conclusion:** `createStubMigration()` remains the right choice for this module. Its transient, unregistered nature is actually appropriate — these are ephemeral, user-specific migrations that shouldn't pollute the global migration registry. The only issue is the orphaned DB tables, which we address with explicit cleanup.

---

## Solution Overview

1. **Create a custom `import_migration_lookup` process plugin** that bypasses the migration plugin manager and directly queries the ID map database table by migration ID. The plugin searches across all `sourceid` columns in the ID map, enabling lookup by any registered source key.

2. **Modify the upstream (referenced) migration's source IDs** so the ID map contains values that the downstream migration can look up. Use **multiple source IDs** where applicable:
   - The "label column" (the CSV column mapped to the entity's label field, e.g., `name` for media).
   - For **media entities**: the CSV column mapped to the media source field (e.g., `field_media_image`), which contains the filename. This allows downstream CSVs to reference media by either name *or* filename (e.g., `"My Photo"` or `"photo.jpg"`).

3. **Post-process migration definitions** in `ExecuteImportForm::submitForm()` to detect cross-migration entity references and inject `import_migration_lookup` into the downstream migration's process pipeline.

---

## Detailed Implementation

### Step 1: Create `ImportMigrationLookup` Process Plugin

**New file:** `src/Plugin/migrate/process/ImportMigrationLookup.php`

This plugin directly queries the Migrate API's SQL ID map table (named `migrate_map_{migration_id}`) without requiring the migration to be registered with the plugin manager.

```php
<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up a destination ID from a previous import migration's ID map.
 *
 * Unlike core's migration_lookup, this plugin does not require migrations
 * to be registered with the plugin manager. It queries the ID map table
 * directly, which works for transient migrations created via
 * createStubMigration().
 *
 * If the lookup fails, the original value passes through unchanged,
 * allowing downstream process plugins (e.g., mukurtu_entity_lookup) to
 * attempt their own resolution.
 *
 * Configuration:
 * - migration_ids: An array of migration IDs to look up.
 *
 * The plugin searches across ALL sourceid columns (sourceid1, sourceid2, etc.)
 * in each migration's ID map table, enabling lookup by any registered source
 * key. For example, a media migration may use both the media name and filename
 * as source IDs, allowing downstream CSVs to reference media by either value.
 */
#[MigrateProcess('import_migration_lookup')]
class ImportMigrationLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // If value is already numeric (an entity ID), pass it through.
    if (is_numeric($value)) {
      return $value;
    }

    $migration_ids = (array) ($this->configuration['migration_ids'] ?? []);

    foreach ($migration_ids as $migration_id) {
      $table = 'migrate_map_' . $migration_id;
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      // Find all sourceid columns in the table (sourceid1, sourceid2, etc.)
      // and search across all of them. This supports upstream migrations with
      // multiple source IDs (e.g., label + filename for media).
      $schema = $this->database->schema();
      $sourceid_columns = [];
      for ($i = 1; $i <= 10; $i++) {
        $col = 'sourceid' . $i;
        if ($schema->fieldExists($table, $col)) {
          $sourceid_columns[] = $col;
        }
        else {
          break;
        }
      }

      foreach ($sourceid_columns as $col) {
        $dest_id = $this->database->select($table, 'm')
          ->fields('m', ['destid1'])
          ->condition($col, $value)
          ->execute()
          ->fetchField();

        if ($dest_id !== FALSE) {
          return $dest_id;
        }
      }
    }

    // Not found — pass the original value through for downstream plugins.
    return $value;
  }

}
```

**Key design decisions:**

- **Direct DB query**: The ID map table name follows the deterministic pattern `migrate_map_{migration_id}`. This is a stable internal convention of Drupal's `Sql` ID map plugin (see `core/modules/migrate/src/Plugin/migrate/id_map/Sql.php`).
- **Multi-column search**: The plugin discovers all `sourceidN` columns in each ID map table and queries each independently. This allows upstream migrations with multiple source IDs (e.g., label + filename for media) to be matched on any of their source values. A user can write either `"My Photo"` (the media name) or `"photo.jpg"` (the filename) in their downstream CSV and both will resolve.
- **Pass-through on miss**: If the value isn't found in any migration's ID map, the original value passes through unchanged. This lets the existing `mukurtu_entity_lookup` plugin try its label-based resolution as a fallback. This also means existing entities (not created by this import) are still referenceable by name/ID/UUID.
- **Numeric bypass**: If the value is already numeric (an entity ID from `uuid_lookup`), skip the migration lookup entirely.
- **Multiple migration IDs**: Supports looking up across several upstream migrations (e.g., both Image media and Audio media migrations).

### Step 2: Modify Source IDs for Upstream Migrations

**File to modify:** `src/Entity/MukurtuImportStrategy.php`

Currently, `toDefinition()` determines source IDs as follows:
1. Entity ID field (e.g., `nid`, `mid`) if mapped → use its CSV column as source ID.
2. UUID field if mapped → use its CSV column.
3. Fallback → `_record_number` (auto-incremented by CSV source plugin).

For cross-migration lookups, we need the ID map to contain values that the downstream migration can look up. We use **multiple source IDs** where applicable:
- The **label column** (the CSV column mapped to the entity's label field, e.g., `Name` → `name` for media).
- For **media entities**: the **media source column** (the CSV column mapped to the media source field, e.g., `File` → `field_media_image`). This contains the filename and allows referencing media by filename.

The ID map table supports composite source IDs (`sourceid1`, `sourceid2`, etc.), and `ImportMigrationLookup` searches across all of them independently.

**New methods on `MukurtuImportStrategy`:**

```php
/**
 * Get the source column mapped to the entity's label field.
 *
 * @return string|null
 *   The CSV column name mapped to the label field, or NULL if not mapped.
 */
public function getLabelSourceColumn(): ?string {
  $entity_type_id = $this->getTargetEntityTypeId();
  $label_key = $this->entityTypeManager()
    ->getDefinition($entity_type_id)
    ->getKey('label');

  if (!$label_key) {
    return NULL;
  }

  foreach ($this->getMapping() as $mapping) {
    if ($mapping['target'] === $label_key) {
      return $mapping['source'];
    }
  }

  return NULL;
}

/**
 * Get the source column mapped to a media entity's source field.
 *
 * For media entities, the "source field" is the field that holds the primary
 * media content (e.g., field_media_image for Image, field_media_document for
 * Document). This is determined via the MediaType's source plugin API:
 * MediaType::getSource()->getConfiguration()['source_field'].
 *
 * The returned column name is the CSV column mapped to the target_id subfield
 * of the media source field. This typically contains the filename.
 *
 * @return string|null
 *   The CSV column name mapped to the media source field, or NULL if:
 *   - The entity type is not 'media'.
 *   - The media type cannot be loaded.
 *   - The source field is not mapped in the CSV.
 */
public function getMediaSourceColumn(): ?string {
  if ($this->getTargetEntityTypeId() !== 'media') {
    return NULL;
  }

  $bundle = $this->getTargetBundle();
  $media_type = $this->entityTypeManager()
    ->getStorage('media_type')
    ->load($bundle);
  if (!$media_type) {
    return NULL;
  }

  $source_field = $media_type->getSource()->getConfiguration()['source_field'] ?? NULL;
  if (!$source_field) {
    return NULL;
  }

  // Look for a mapping to the source field's target_id subfield
  // (e.g., field_media_image/target_id) or the base field itself.
  foreach ($this->getMapping() as $mapping) {
    $target = $mapping['target'];
    if ($target === $source_field || $target === $source_field . '/target_id') {
      return $mapping['source'];
    }
  }

  return NULL;
}
```

**Modify `toDefinition()` to accept an array of `$lookup_source_ids`:**

```php
public function toDefinition(FileInterface $file, array $lookup_source_ids = []): array {
  $mapping = $this->getMapping();
  $entity_type_id = $this->getTargetEntityTypeId();
  $bundle = $this->getTargetBundle();
  $id_key = $this->entityTypeManager()->getDefinition($entity_type_id)->getKey('id');
  $uuid_key = $this->entityTypeManager()->getDefinition($entity_type_id)->getKey('uuid');
  $process = $this->getProcess();

  $ids = [];
  // Entity ID has priority.
  if (!empty($process[$id_key])) {
    $ids = array_filter(array_map(fn($v) => $v['target'] == $id_key ? $v['source'] : NULL, $mapping));
  }

  // UUID has next priority.
  if (empty($ids) && !empty($process[$uuid_key])) {
    $ids = array_filter(array_map(fn ($v) => $v['target'] == $uuid_key ? $v['source'] : NULL, $mapping));
  }

  // If we have no ID or UUID, use the lookup columns (for cross-migration
  // references) or fallback to _record_number.
  if (empty($ids)) {
    if (!empty($lookup_source_ids)) {
      $ids = $lookup_source_ids;
    }
    else {
      $ids[] = '_record_number';
    }
  }

  return [
    'id' => $this->getDefinitionId($file),
    // ... rest unchanged
  ];
}
```

The `$lookup_source_ids` array is determined and passed in from `ExecuteImportForm` (Step 3 below). When empty, behavior is unchanged from today. When populated, all columns in the array become source IDs in the CSV source plugin, creating composite keys in the ID map table.

Note: `toDefinition()` currently has no second parameter, and the `MukurtuImportStrategyInterface` defines it as `public function toDefinition(FileInterface $file)`. The interface will need updating too.

### Step 3: Detect Cross-Migration Dependencies and Inject Lookups

**File to modify:** `src/Form/ExecuteImportForm.php`

The `submitForm()` method currently builds migration definitions independently and then runs them. We need a post-processing step that:

1. **Builds an index** of which entity types are being created by which migrations.
2. **Scans each migration's process pipeline** for entity reference fields that target those entity types.
3. **Modifies the upstream migration's source IDs** to use the label column.
4. **Injects `import_migration_lookup`** into the downstream migration's process pipeline.

```php
/**
 * {@inheritdoc}
 */
public function submitForm(array &$form, FormStateInterface $form_state): void {
  $metadata_files = array_keys($this->getMetadataFileWeights());

  // Phase 1: Build all migration definitions.
  $configs_by_fid = [];
  $files_by_fid = [];
  foreach ($metadata_files as $fid) {
    $configs_by_fid[$fid] = $this->getImportConfig($fid);
    $files_by_fid[$fid] = $this->entityTypeManager->getStorage('file')->load($fid);
  }

  // Phase 2: Detect cross-migration dependencies.
  // Build an index of entity_type → [fid => config].
  $entity_type_index = [];
  foreach ($configs_by_fid as $fid => $config) {
    $entity_type = $config->getTargetEntityTypeId();
    $entity_type_index[$entity_type][$fid] = $config;
  }

  // Determine which upstream migrations need lookup-based source IDs.
  // Key: fid, Value: array of source column names to use as IDs.
  $upstream_lookup_columns = [];
  foreach ($configs_by_fid as $fid => $config) {
    $this->detectUpstreamDependencies(
      $config,
      $entity_type_index,
      $upstream_lookup_columns
    );
  }

  // Phase 3: Build the final migration definitions.
  $migration_definitions = [];
  foreach ($metadata_files as $fid) {
    $config = $configs_by_fid[$fid];
    $file = $files_by_fid[$fid];
    if (!$file) {
      continue;
    }

    // Pass the lookup columns if this migration is referenced by others.
    $lookup_columns = $upstream_lookup_columns[$fid] ?? [];
    $definition = $config->toDefinition($file, $lookup_columns)
      + ['mukurtu_import_message' => $this->getImportRevisionMessage()];

    $migration_definitions[$fid] = $definition;
  }

  // Phase 4: Inject import_migration_lookup into downstream definitions.
  $this->injectCrossMigrationLookups(
    $migration_definitions,
    $configs_by_fid,
    $entity_type_index
  );

  // Phase 5: Run the migrations.
  $migrate_message = new MigrateMessage();
  $ordered_definitions = array_values($migration_definitions);
  $bootstrap_migration = $this->migrationPluginManager
    ->createStubMigration(reset($ordered_definitions));
  $executable = new ImportBatchExecutable(
    $bootstrap_migration,
    $migrate_message,
    $this->keyValue,
    $this->time,
    $this->translation,
    $this->migrationPluginManager,
  );

  try {
    $executable->batchImportMultiple($ordered_definitions);
  }
  catch (Exception $e) {
    // @todo Handle gracefully.
  }

  $form_state->setRedirect('mukurtu_import.import_results');
}
```

**New helper methods on `ExecuteImportForm`:**

```php
/**
 * Detect upstream migrations that downstream entity references depend on.
 *
 * Scans a migration config's field mappings. For each entity reference field,
 * checks if any other migration in this import creates that entity type.
 * If so, records the upstream migration's lookup columns (label and, for
 * media, the media source field column) so they can be used as source IDs.
 *
 * @param \Drupal\mukurtu_import\MukurtuImportStrategyInterface $config
 *   The import config to scan.
 * @param array $entity_type_index
 *   Index of entity_type => [fid => config].
 * @param array &$upstream_lookup_columns
 *   Accumulator: fid => array of source column names.
 */
protected function detectUpstreamDependencies(
  MukurtuImportStrategyInterface $config,
  array $entity_type_index,
  array &$upstream_lookup_columns
): void {
  $entity_type_id = $config->getTargetEntityTypeId();
  $bundle = $config->getTargetBundle();
  $field_defs = $this->entityFieldManager
    ->getFieldDefinitions($entity_type_id, $bundle);

  foreach ($config->getMapping() as $mapping) {
    $target = explode('/', $mapping['target'], 2)[0];
    $field_def = $field_defs[$target] ?? NULL;
    if (!$field_def) {
      continue;
    }

    // Only care about entity reference fields.
    $field_type = $field_def->getType();
    if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
      continue;
    }

    $ref_type = $field_def->getSetting('target_type');
    if (!isset($entity_type_index[$ref_type])) {
      continue;
    }

    // This field references an entity type created by another migration.
    foreach ($entity_type_index[$ref_type] as $upstream_fid => $upstream_config) {
      $columns = [];

      // Always include the label column.
      $label_column = $upstream_config->getLabelSourceColumn();
      if ($label_column) {
        $columns[] = $label_column;
      }

      // For media entities, also include the media source field column
      // (e.g., the filename column) so media can be referenced by filename.
      $media_source_column = $upstream_config->getMediaSourceColumn();
      if ($media_source_column && !in_array($media_source_column, $columns)) {
        $columns[] = $media_source_column;
      }

      if (!empty($columns)) {
        // Merge with any previously detected columns for this fid.
        $existing = $upstream_lookup_columns[$upstream_fid] ?? [];
        $upstream_lookup_columns[$upstream_fid] = array_unique(
          array_merge($existing, $columns)
        );
      }
    }
  }
}

/**
 * Inject import_migration_lookup into downstream process pipelines.
 *
 * For each migration definition, scans entity reference process pipelines
 * to find mukurtu_entity_lookup steps. When the target entity type matches
 * an upstream migration, inserts import_migration_lookup before the
 * entity lookup step.
 *
 * @param array &$migration_definitions
 *   The migration definitions array, keyed by fid.
 * @param array $configs_by_fid
 *   Import configs keyed by fid.
 * @param array $entity_type_index
 *   Index of entity_type => [fid => config].
 */
protected function injectCrossMigrationLookups(
  array &$migration_definitions,
  array $configs_by_fid,
  array $entity_type_index
): void {
  foreach ($migration_definitions as $fid => &$definition) {
    $config = $configs_by_fid[$fid];
    $entity_type_id = $config->getTargetEntityTypeId();
    $bundle = $config->getTargetBundle();
    $field_defs = $this->entityFieldManager
      ->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($definition['process'] as $target_field => &$process) {
      // Normalize to array of steps.
      if (!is_array($process) || !isset($process[0])) {
        continue;
      }

      // Find the target field definition.
      $base_target = explode('/', $target_field, 2)[0];
      $field_def = $field_defs[$base_target] ?? NULL;
      if (!$field_def) {
        continue;
      }

      $field_type = $field_def->getType();
      if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
        continue;
      }

      $ref_type = $field_def->getSetting('target_type');
      if (!isset($entity_type_index[$ref_type])) {
        continue;
      }

      // Collect the migration IDs that create this entity type.
      $upstream_migration_ids = [];
      foreach ($entity_type_index[$ref_type] as $upstream_fid => $upstream_config) {
        if ($upstream_fid === $fid) {
          // Don't self-reference.
          continue;
        }
        if (isset($migration_definitions[$upstream_fid])) {
          $upstream_migration_ids[] = $migration_definitions[$upstream_fid]['id'];
        }
      }

      if (empty($upstream_migration_ids)) {
        continue;
      }

      // Find the position of mukurtu_entity_lookup or mukurtu_entity_generate
      // and insert import_migration_lookup before it.
      $lookup_step = [
        'plugin' => 'import_migration_lookup',
        'migration_ids' => $upstream_migration_ids,
      ];

      $insert_position = NULL;
      foreach ($process as $i => $step) {
        if (is_array($step) && isset($step['plugin'])
          && in_array($step['plugin'], ['mukurtu_entity_lookup', 'mukurtu_entity_generate'])) {
          $insert_position = $i;
          break;
        }
      }

      if ($insert_position !== NULL) {
        array_splice($process, $insert_position, 0, [$lookup_step]);
      }
    }
    unset($process);
  }
  unset($definition);
}
```

### Step 4: Update `MukurtuImportStrategyInterface`

**File to modify:** `src/MukurtuImportStrategyInterface.php`

Add the new methods and update `toDefinition()` signature:

```php
/**
 * Get the source column mapped to the entity's label field.
 *
 * @return string|null
 *   The CSV column name mapped to the label field, or NULL.
 */
public function getLabelSourceColumn(): ?string;

/**
 * Get the source column mapped to a media entity's source field.
 *
 * @return string|null
 *   The CSV column name mapped to the media source field, or NULL if not
 *   applicable (non-media entity type, unmapped source field, etc.).
 */
public function getMediaSourceColumn(): ?string;

/**
 * Generate a Migrate API definition for a given file.
 *
 * @param \Drupal\file\FileInterface $file
 *   The import input file.
 * @param array $lookup_source_ids
 *   (optional) CSV column names to use as source IDs for cross-migration
 *   lookups. When provided and no entity ID/UUID is mapped, these columns
 *   are used as the source IDs instead of _record_number. Multiple columns
 *   create a composite source key in the ID map, enabling lookup by any
 *   of the values (e.g., media name or filename).
 *
 * @return array
 *   The migration definition array.
 */
public function toDefinition(FileInterface $file, array $lookup_source_ids = []): array;
```

### Step 5: Clean Up ID Map Tables After Import

**File to modify:** `src/ImportBatchExecutable.php`

As discussed in the "Artifacts Left Behind" section above, each migration run creates `migrate_map_*` and `migrate_message_*` tables that are never cleaned up. Since the cross-migration lookup feature relies on these tables persisting across migrations within a single batch, we must clean them up **after** the entire batch completes — not after each individual migration.

Add cleanup in the `batchFinishedImport()` static callback:

```php
public static function batchFinishedImport($success, $results, $operations): void {
  // ... existing message/error handling ...

  // Clean up ID map tables for all migrations in this batch.
  // These are no longer needed after the import is complete.
  $database = \Drupal::database();
  $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
  foreach ($results['definitions'] ?? [] as $definition) {
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $migration->getIdMap()->destroy();
  }
}
```

This requires that the batch `$results` array carries the migration definitions. In `batchProcessImportDefinition()`, add:

```php
$context['results']['definitions'][] = $definition;
```

**Note:** This cleanup step is independent of the cross-migration lookup feature and fixes a pre-existing issue. It could be done as a separate commit.

---

## Process Pipeline: Before and After

### Before (current)

For `field_media_assets` (entity reference to media):

```
CSV value: "My Photo"
    ↓
[explode]  →  "My Photo"
    ↓
[uuid_lookup]  →  not a UUID  →  "My Photo"
    ↓
[mukurtu_entity_lookup by name]  →  searches for media named "My Photo"
    ↓
    Result: entity ID (or exception if ambiguous/not found)
```

### After (with cross-migration lookup)

```
CSV value: "My Photo"  (or "photo.jpg")
    ↓
[explode]  →  "My Photo"  (or "photo.jpg")
    ↓
[uuid_lookup]  →  not a UUID  →  "My Photo"  (or "photo.jpg")
    ↓
[import_migration_lookup]  →  queries migrate_map_{media_migration_id}
                               WHERE sourceid1 = "My Photo" (label match)
                                  OR sourceid2 = "My Photo" (filename match)
    ↓
    Found?  →  returns destination media ID (e.g., 5)
    Not found?  →  passes value through
    ↓
[mukurtu_entity_lookup by name]  →  if already numeric, validates as entity ID
                                     if string, searches for media named "My Photo"
    ↓
    Result: entity ID
```

The `import_migration_lookup` acts as an early resolver that catches entities created in the same import session. It searches across all source ID columns (label, filename, etc.) so the downstream CSV can reference media by **either name or filename**. If the entity wasn't part of this import (it was pre-existing), the value falls through to `mukurtu_entity_lookup` which handles it as before.

---

## How Source IDs Change

### Upstream migration (Media.csv) — Before

```php
'source' => [
  'plugin' => 'csv',
  'ids' => ['_record_number'],    // ← meaningless auto-increment
  'create_record_number' => TRUE,
  'record_number_field' => '_record_number',
]
```

ID map table: `sourceid1 = 1, destid1 = 5`

### Upstream migration (Media.csv) — After

```php
'source' => [
  'plugin' => 'csv',
  'ids' => ['Name', 'File'],       // ← label column + media source field column
  'create_record_number' => TRUE,   // kept for compatibility
  'record_number_field' => '_record_number',
]
```

ID map table: `sourceid1 = "My Photo", sourceid2 = "photo.jpg", destid1 = 5`

Now `import_migration_lookup` can look up either `"My Photo"` → `5` or `"photo.jpg"` → `5`.

**Important:** This change only applies when:
- No entity ID (e.g., `mid`) or UUID column is mapped (otherwise those have priority).
- Another migration in the batch references this entity type.
- For media: the media source field column is only included if it's mapped in the CSV.
- For non-media entity types: only the label column is used (single source ID, as before).

If the user HAS mapped `mid` or `uuid`, those remain the source IDs and the downstream CSV should use those values. This is already the expected behavior.

---

## Edge Cases

### Multiple upstream migrations for the same entity type

Example: Both `Image Media.csv` and `Audio Media.csv` create media entities. `Digital Heritage.csv` has `field_media_assets` referencing media. The `import_migration_lookup` plugin accepts an array of `migration_ids` and tries each until it finds a match.

### Self-referencing migrations

A single CSV might create nodes that reference other nodes (e.g., "Related Items"). The `injectCrossMigrationLookups` method skips self-references (`$upstream_fid === $fid`) since the entity doesn't exist yet when its own row is being processed. This case falls through to `mukurtu_entity_lookup`.

### Pre-existing entities

If the user writes the name of an entity that already existed (not created by this import), `import_migration_lookup` won't find it in the ID map and will pass the value through. `mukurtu_entity_lookup` then finds it by label as before.

### Filename-based media references

A user can write `"photo.jpg"` in their Digital Heritage CSV's `field_media_assets` column instead of the media's name. `ImportMigrationLookup` will find it in `sourceid2` (the filename column) of the upstream media migration's ID map. If the filename isn't found (e.g., the media source field wasn't mapped, or the media was pre-existing), the value falls through to `mukurtu_entity_lookup`.

Note: For `remote_video` media, the source field (`field_media_oembed_video`) contains the video URL, not a filename. The same mechanism applies — the user could reference a remote video by its URL.

### Non-unique values in upstream CSV

With composite source IDs (e.g., `Name` + `File`), two rows must have the same *combination* of all source ID values to collide. This means two media with the same name but different files are distinct entries in the ID map — an improvement over single-column source IDs. However, `ImportMigrationLookup` queries each column independently, so if two rows share the same filename but have different names, searching by that filename will match the first result found.

### Upstream migration has entity ID/UUID mapped

When the user maps `mid` or `uuid` in the upstream CSV, those columns become the source IDs. The downstream CSV must use those IDs/UUIDs to reference entities, which is the same as today. No `import_migration_lookup` injection is needed in this case (but it doesn't hurt — the lookup just won't match non-numeric/non-UUID values).

Actually, we should still inject `import_migration_lookup` even in this case, because the user might still reference by name. The lookup column logic should handle this: if the upstream uses `mid` as its source ID, we can't look up by name via the ID map. But we CAN still fall through to `mukurtu_entity_lookup`. So the behavior is correct either way.

### CulturalProtocols subfield

The `cultural_protocol` field type uses entity references for its `protocols` subfield. The same cross-migration lookup applies if a Protocol.csv is also being imported.

---

## Files Changed Summary

| File | Change |
|---|---|
| `src/Plugin/migrate/process/ImportMigrationLookup.php` | **NEW** — Custom process plugin (multi-column search) |
| `src/Entity/MukurtuImportStrategy.php` | Add `getLabelSourceColumn()`, `getMediaSourceColumn()`, modify `toDefinition()` signature |
| `src/MukurtuImportStrategyInterface.php` | Update interface for new methods and modified `toDefinition()` |
| `src/Form/ExecuteImportForm.php` | Add `detectUpstreamDependencies()`, `injectCrossMigrationLookups()`, `sortByDependencies()`, modify `submitForm()` |
| `src/ImportBatchExecutable.php` | Add ID map table cleanup in `batchFinishedImport()` |

---

## Migration Execution Order (Auto-Sorting)

The system supports file ordering via the weight-based drag table in `ImportFileSummaryForm`. However, with cross-migration dependencies, **execution order matters**: upstream migrations (e.g., Media) must run before downstream migrations (e.g., Digital Heritage). The user should not have to manually sort these — since we already detect cross-migration dependencies in `detectUpstreamDependencies()`, we should auto-sort.

### Approach

After Phase 2 (detecting dependencies) in `ExecuteImportForm::submitForm()`, reorder the `$metadata_files` array so that upstream migrations come before downstream ones. This is a topological sort based on the detected dependency graph.

Add a new Phase 2.5 step in `submitForm()` between dependency detection and definition building:

```php
// Phase 2.5: Reorder files so upstream migrations run first.
$metadata_files = $this->sortByDependencies($metadata_files, $configs_by_fid, $entity_type_index);
```

**New method on `ExecuteImportForm`:**

```php
/**
 * Sort file IDs so upstream (referenced) migrations run before downstream.
 *
 * Uses a topological sort: for each file whose entity reference fields
 * target an entity type created by another file in this import, the
 * referenced file must come first.
 *
 * Falls back to the user's original weight-based ordering for files
 * with no dependency relationship.
 *
 * @param array $metadata_files
 *   File IDs in the user's original weight order.
 * @param array $configs_by_fid
 *   Import configs keyed by file ID.
 * @param array $entity_type_index
 *   Index of entity_type => [fid => config].
 *
 * @return array
 *   File IDs reordered so dependencies are satisfied.
 */
protected function sortByDependencies(
  array $metadata_files,
  array $configs_by_fid,
  array $entity_type_index
): array {
  // Build a dependency graph: fid => [fids it depends on].
  $dependencies = array_fill_keys($metadata_files, []);

  foreach ($configs_by_fid as $fid => $config) {
    $entity_type_id = $config->getTargetEntityTypeId();
    $bundle = $config->getTargetBundle();
    $field_defs = $this->entityFieldManager
      ->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($config->getMapping() as $mapping) {
      $target = explode('/', $mapping['target'], 2)[0];
      $field_def = $field_defs[$target] ?? NULL;
      if (!$field_def) {
        continue;
      }

      $field_type = $field_def->getType();
      if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
        continue;
      }

      $ref_type = $field_def->getSetting('target_type');
      if (!isset($entity_type_index[$ref_type])) {
        continue;
      }

      foreach (array_keys($entity_type_index[$ref_type]) as $upstream_fid) {
        if ($upstream_fid !== $fid) {
          $dependencies[$fid][] = $upstream_fid;
        }
      }
    }
    $dependencies[$fid] = array_unique($dependencies[$fid]);
  }

  // Topological sort (Kahn's algorithm).
  // Preserve the user's weight order as a tiebreaker.
  $position = array_flip($metadata_files);
  $sorted = [];
  $remaining = $dependencies;

  while (!empty($remaining)) {
    // Find all files with no unresolved dependencies.
    $ready = [];
    foreach ($remaining as $fid => $deps) {
      $unresolved = array_intersect($deps, array_keys($remaining));
      if (empty($unresolved)) {
        $ready[] = $fid;
      }
    }

    if (empty($ready)) {
      // Circular dependency — fall back to original order for remaining.
      usort($remaining_keys = array_keys($remaining), fn($a, $b) => $position[$a] <=> $position[$b]);
      $sorted = array_merge($sorted, $remaining_keys);
      break;
    }

    // Sort ready files by their original weight-based position.
    usort($ready, fn($a, $b) => $position[$a] <=> $position[$b]);

    foreach ($ready as $fid) {
      $sorted[] = $fid;
      unset($remaining[$fid]);
    }
  }

  return $sorted;
}
```

**Key behaviors:**

- **Upstream migrations run first**: If Media.csv creates media and Digital Heritage.csv references media, Media.csv is sorted before Digital Heritage.csv regardless of the user's drag-and-drop ordering.
- **User's weight order preserved as tiebreaker**: Among files with no dependency relationship (e.g., two independent CSVs), the user's original ordering is respected.
- **Circular dependencies handled gracefully**: If two files reference each other (unlikely but possible), the sort falls back to the user's original order for those files.
- **No UI changes needed**: The weight-based drag table in `ImportFileSummaryForm` remains as-is. The auto-sort is applied transparently at execution time.

---

## Implementation Todo List

### Phase 1: New Process Plugin

- [x] Create `src/Plugin/migrate/process/ImportMigrationLookup.php`
  - [x] Implement `ContainerFactoryPluginInterface` with `Connection` dependency
  - [x] Implement `transform()`: numeric bypass, iterate `migration_ids`, query `migrate_map_*` tables, pass-through on miss
  - [x] Update `transform()` to discover and search across all `sourceidN` columns (not just `sourceid1`) to support composite source IDs (label + filename)

### Phase 2: Strategy Entity Changes

- [x] Add `getLabelSourceColumn()` to `src/MukurtuImportStrategyInterface.php`
- [x] Add `getLabelSourceColumn()` implementation to `src/Entity/MukurtuImportStrategy.php`
  - [x] Look up the entity type's label key via `EntityTypeInterface::getKey('label')`
  - [x] Find and return the CSV source column mapped to that label key
- [ ] Add `getMediaSourceColumn()` to `src/MukurtuImportStrategyInterface.php`
- [ ] Add `getMediaSourceColumn()` implementation to `src/Entity/MukurtuImportStrategy.php`
  - [ ] Return `NULL` for non-media entity types
  - [ ] Load the `MediaType` entity and get the source field name via `getSource()->getConfiguration()['source_field']`
  - [ ] Find and return the CSV column mapped to the source field (or its `target_id` subfield)
- [ ] Update `toDefinition()` signature in `src/MukurtuImportStrategyInterface.php` to accept `array $lookup_source_ids = []`
- [ ] Update `toDefinition()` in `src/Entity/MukurtuImportStrategy.php`
  - [ ] Accept the new `array $lookup_source_ids` parameter
  - [ ] When no entity ID or UUID is mapped, use `$lookup_source_ids` as the source IDs (falling back to `['_record_number']` when empty)

### Phase 3: ExecuteImportForm — Dependency Detection and Injection

- [x] Inject `EntityFieldManagerInterface` into `ExecuteImportForm` (if not already available)
- [x] Restructure `submitForm()` into the 5-phase approach:
  - [x] Phase 1: Build configs and load files for all metadata files
  - [x] Phase 2: Build entity type index and detect upstream dependencies via `detectUpstreamDependencies()`
  - [x] Phase 2.5: Auto-sort files via `sortByDependencies()`
  - [ ] Phase 3: Build migration definitions, passing `$lookup_columns` array to `toDefinition()` for upstream migrations
  - [x] Phase 4: Inject `import_migration_lookup` into downstream definitions via `injectCrossMigrationLookups()`
  - [x] Phase 5: Run migrations (existing batch logic)
- [ ] Update `detectUpstreamDependencies()` to collect arrays of columns per upstream fid
  - [x] For each mapped field, check if it's an entity reference (`entity_reference` or `entity_reference_revisions`)
  - [x] If the referenced entity type is created by another migration in this import, record the upstream migration's label column
  - [ ] Also record the upstream migration's media source column (if applicable) via `getMediaSourceColumn()`
- [x] Implement `injectCrossMigrationLookups()`
  - [x] For each migration definition, scan process pipelines for entity reference fields
  - [x] For matching fields, collect upstream migration IDs (excluding self-references)
  - [x] Find the `mukurtu_entity_lookup` or `mukurtu_entity_generate` step position
  - [x] Insert `import_migration_lookup` step before it with the upstream migration IDs
- [x] Implement `sortByDependencies()`
  - [x] Build dependency graph from entity reference field mappings
  - [x] Topological sort (Kahn's algorithm) with user's weight order as tiebreaker
  - [x] Handle circular dependencies by falling back to user's original order

### Phase 4: ID Map Cleanup

- [x] In `ImportBatchExecutable::batchProcessImportDefinition()`, store the migration definition in `$context['results']['definitions'][]`
- [x] In `ImportBatchExecutable::batchFinishedImport()`, iterate stored definitions and call `$migration->getIdMap()->destroy()` on each

### Phase 5: Verification (Manual Testing Required)

- [ ] Manual test: upload a Media CSV and a Digital Heritage CSV that references media by **name**
  - [ ] Verify media entities are created first (auto-sort)
  - [ ] Verify DH nodes have correct `field_media_assets` references
  - [ ] Verify `migrate_map_*` and `migrate_message_*` tables are cleaned up after import
- [ ] Manual test: upload a Media CSV and a Digital Heritage CSV that references media by **filename** (e.g., `"photo.jpg"` instead of the media name)
  - [ ] Verify the filename resolves to the correct media entity via `sourceid2` lookup
- [ ] Manual test: upload only a single CSV (no cross-migration references) to verify no regressions
- [ ] Manual test: reference a pre-existing media entity by name to verify fallback to `mukurtu_entity_lookup` still works
