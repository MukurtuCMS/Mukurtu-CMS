# Import Translation Support

This documents how `mukurtu_import` (CSV/spreadsheet import) handles targeting a *translation* of existing content, rather than always creating or overwriting the default-language entity (#1260 Phase 5, import track). It's the counterpart to `docs/export-translation.md`'s export track.

Only the CSV import path is covered. `mukurtu_migrate` (legacy Drupal 7 migration) needs nothing here — the profile has no expectation of migrating D7 multilingual content, and will revisit only if that's actually requested (see #2078).

## Turning it on: `destination.translations`

Drupal core's own `entity:$entity_type_id` migrate destination already knows how to target a translation — set `translations: true` on the destination and, for any row whose mapped langcode differs from an existing entity's own language, it calls `addTranslation()`/`getTranslation()` before writing that row's fields, instead of overwriting the base entity. Mukurtu never turned this on; `MukurtuImportStrategy::toDefinition()` now does, **strictly opt-in**, gated on both:

- The strategy's mapping includes a column mapped to the entity type's langcode field (already a normal, listed mapping target — `ImportFormTrait::buildTargetOptions()` labels it "... (langcode)" to disambiguate it from any other field that happens to also be called "Language").
- The target bundle actually has content translation enabled (`content_translation.manager`'s `isEnabled($entity_type_id, $bundle)`).

Neither condition met → `translations` is never set, and behavior is byte-identical to before this existed. See `MukurtuImportStrategy::isTranslationImport()`.

## The non-translatable-field guard

Drupal core's `EntityContentBase::updateEntity()` writes every field named in `overwrite_properties` through whichever translation object the row resolved to — with **no guard** for fields that aren't marked translatable. Those fields' storage is shared across every translation of an entity, so writing one through a translation object silently overwrites it for every other translation too. `field_cultural_protocols` (sharing settings + protocol assignment) is the concrete example on Mukurtu content — protocol/sharing settings apply to the whole entity, not per-language, so they're deliberately not translatable.

**The fix is a static, strategy-wide exclusion**, not a per-row one: once a strategy is a translation import (per the gating above), `MukurtuImportStrategy::getOverwriteProperties()` drops every non-translatable field from the writable-fields list entirely, for the whole strategy.

**What this does and doesn't affect** — traced against Drupal core's own `Entity::getEntity()`:

- `overwrite_properties` (and therefore this guard) is only consulted on the **update** path — `getEntity()` calls `updateEntity()` only when a matching entity already exists for the row (loaded by ID/UUID/identifier column).
- A row that creates **brand-new** content never touches `overwrite_properties` at all — `getEntity()` builds it via `$storage->create($row->getDestination())`, which writes every mapped field, including non-translatable ones, exactly as before. So a translation-enabled strategy can still create new content with protocols set, in the same file.
- The real, narrower limitation: once a strategy is a translation import, it can no longer **update** a non-translatable field on already-existing content through that strategy — regardless of whether the row targeting a translation or the entity's own original language. If you need to change `field_cultural_protocols` (or any other non-translatable field) on existing content, do that through a separate, non-translation-targeting import first.

In practice this matches how translation work tends to happen anyway: content (and its protocol/sharing settings) gets created first, and translations get added later, often as a separate pass.

The correct-but-costlier alternative — a custom per-entity-type destination plugin that only skips non-translatable fields on rows updating an existing entity's *non-original* translation, leaving original-language updates unaffected — was considered and declined: it would mean subclassing every core/contrib migrate destination plugin Mukurtu's ~6 importable entity types use (node, media, taxonomy_term, paragraph, and the custom roundtrip entity types), for a narrower edge case than the static guard already handles reasonably.

## Out of scope

- Legacy D7 migration translation support (`mukurtu_migrate`) — not planned; see #2078.
- Anything letting one strategy update a non-translatable field on existing content while also being a translation import — use two passes instead (see above).
