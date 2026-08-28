# Content Language Policy

This document codifies how Views, Search API indexes, and Facets should handle language on a multilingual Mukurtu site. It exists because the policy grew inconsistently over time (see GitHub issues #1159/#1188): some views strictly match the current interface language and hide anything untranslated, some match the site's default language instead, and several have no language awareness at all and show every translation mixed together.

## The policy

When a visitor is viewing the site in language X and a piece of content has no translation into X, the content should still appear, showing its default-language version, with a visible "not yet translated" indicator. Content should never silently disappear from a listing just because it lacks a translation.

This is implemented with two different, existing Drupal/contrib mechanisms depending on how the view is built. Neither requires a custom Views filter or Facets processor.

### Search API-backed views and facets

Use the `language_with_fallback` property. It's provided by Search API's `LanguageWithFallback` processor, which is already enabled (locked/hidden) on every Mukurtu index and shipped as a `string`-typed field in each index's `config/install`. For each indexed item, this property resolves to the item's own language plus every language that falls back to it, so filtering `language_with_fallback = <current content language>` returns exactly one item per entity: the translation if one exists, otherwise the fallback (default-language) item.

**Use `language_content`, not `language_interface`, for the same reason as the plain-entity recipe below** — `mukurtu_multilingual.install` deliberately negotiates it as a separate type. Filter on the field with `plugin_id: search_api_string`, `operator: '='`, `value: {min: '', max: '', value: '***LANGUAGE_language_content***'}` (a plain `string` field uses Search API's generic string filter, not a dedicated language one — see `mukurtu_taxonomy_references.yml`'s `entity_type` filter for the same plugin's shape on a different field) — instead of the raw `search_api_language` property, which doesn't fall back and hides untranslated content outright.

### Plain-entity views (no Search API)

Use core's own recipe, the same structural pattern `core/modules/media_library` ships:

- A `default_langcode = 1` filter, so each entity contributes exactly one row (its original/default translation) — no duplicate rows per translation.
- The display's `rendering_language` set to `***LANGUAGE_language_content***`.

**Use `language_content`, not `language_interface`, even though `media_library`'s own filter uses `language_interface`.** `mukurtu_multilingual.install` deliberately configures `language_content` as a separately-negotiated language type from `language_interface` (so an admin's interface-language preference doesn't also silently override which content translation renders) — copying `media_library`'s literal token would bypass that.

`EntityViewBuilder` resolves the actual rendered translation via `entity.repository`'s `getTranslationFromContext()`, which already implements "current translation if it exists, else the default" — so this combination gives fallback behavior for free, no custom code.

### Choosing between the two

If the view **sorts or filters on translatable field values** (e.g. alphabetical by title, filtered by a translatable description), it must use the Search API path. The plain-entity `default_langcode = 1` filter selects the *original*-language row, so sorting or filtering on it operates on original-language values even when rendering shows the current translation.

If the view only lists/renders entities without sorting or filtering on translatable text, either mechanism works; prefer whichever the view already uses (Search API vs. plain Views) to avoid an unrelated migration.

### The "not yet translated" indicator

Implemented once, in `mukurtu_core` via `hook_entity_view_alter()`, rather than per content type or per view. It fires whenever the entity being rendered has no translation into the current content language. Don't duplicate this per view or per row template.

## Exemptions

Admin-only views that intentionally list content across all languages for editorial/management purposes (not visitor-facing browse/search) may skip this policy. Document the reason in `ViewLanguageFallbackCoverageTest::EXEMPTIONS` (below) — not as a YAML comment, since a normal Drupal config export/import cycle strips comments from `.yml` files and they won't survive it.

## Automated check

`modules/mukurtu_multilingual/tests/src/Unit/ViewLanguageFallbackCoverageTest.php` runs in CI on every PR. It scans every shipped `views.view.*.yml` (`config/install` and `config/optional`, profile-wide) and fails the build if a view implements neither fallback mechanism above **and** has no entry in its `EXEMPTIONS` list. Adding a new view (or editing an existing one's language handling) that doesn't satisfy either condition breaks CI — either implement the fallback pattern, or add a keyed, reasoned entry to `EXEMPTIONS`.

## Checklist for new views/facets

- [ ] Does this view show content to site visitors (not just admins/editors)? If yes, it needs the fallback policy above.
- [ ] Does it sort or filter on translatable field values? If yes, use the Search API path even if a plain-entity view would otherwise be simpler.
- [ ] If it's exempt, is the reason documented in `ViewLanguageFallbackCoverageTest::EXEMPTIONS`?
