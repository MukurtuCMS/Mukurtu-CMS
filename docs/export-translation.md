# Export Translation Support

This documents how `mukurtu_export` handles translated content (#1260 Phase 5, export track). It exists because export previously always emitted an entity's default/original-language field values, regardless of which translation a user actually wanted.

## Item identity

Every exporter source (`AdHocExporterSource`, `ExportListSource`) hands `CSV.php` an `entity_type_id => [key => key]` map, same as before. A key is now either:

- A bare entity ID — export that entity's original language. Unchanged from before this feature existed.
- A composite `"$id:$langcode"` key, built via `ExportItemIdentity::encode()`/parsed via `::decode()` — export the specific translation. `CSV::batchExport()` swaps in `$entity->getTranslation($langcode)` when it exists, and falls back to the entity's own language otherwise (never a hard failure).

Only one format is defined, in one place (`ExportItemIdentity`), so `CSV.php`'s batch logic never needs to know which source produced a given key.

## Two ways a composite key gets produced

- **VBO bulk export** (`AdHocExportStartController::startBulk()`): the manage-content view's selection already carries each row's langcode; it's encoded directly since this path is ephemeral (tempstore-backed, nothing persisted).
- **Saved Export Lists**: `ExportList` gained an additive `item_languages` base field (`"$entity_type:$id" => $langcode`), separate from the existing `items` field. `items`' shape is intentionally untouched — `ExportListRemoveItemsForm`/`ExportListListBuilder` match/remove by bare entity ID, so encoding language into `items`' own keys would have broken removal. An item with no `item_languages` entry exports in its original language, exactly as before this field existed — no update hook was needed to migrate existing data, only the standard field-installation update hook for the schema itself.
  - **Scope boundary**: one langcode per (entity_type, id) per list. Adding the same item again with a different language choice overwrites the previous one rather than creating a second entry.

The language picker (`ExportListAddItemForm`/`ExportListAddNodeForm`) only appears when the entity actually has more than one translation, and defaults to whichever translation the user is currently viewing.

## Referenced entities

A translated row's *own* fields translate for free — `$entity->get($field_name)` is already scoped to whichever translation object `CSV::export()` was called with. But fields that reference *other* entities (taxonomy terms, protocols, usernames) previously always loaded that referenced entity fresh via a bare `->load()`, showing its own default-language name regardless of the row's language.

`CsvEntityFieldExportEventSubscriber` now resolves these through `EntityTranslationResolver::loadTranslated()` (`mukurtu_core`), using the row's langcode (`EntityFieldExportEvent::getLangcode()`, derived from the row entity itself — `NULL` unless it's a non-original translation). When a referenced entity is additionally queued for its own export (`exportAdditionalEntity()`), it's queued under a composite key too, so the whole reference chain stays in the same language as the row that pulled it in.

## Out of scope

- The import track (translations on the way *in*) — separate follow-up.
- Exporting the same item in more than one language within a single saved list.
