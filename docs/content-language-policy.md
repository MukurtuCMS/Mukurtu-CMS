# Content Language Policy

This document codifies how Views, Search API indexes, and Facets should handle language on a multilingual Mukurtu site. It exists because the policy grew inconsistently over time (see GitHub issues #1159/#1188): some views strictly match the current interface language and hide anything untranslated, some match the site's default language instead, and several have no language awareness at all and show every translation mixed together.

## The policy

When a visitor is viewing the site in language X and a piece of content has no translation into X, the content should still appear, showing its default-language version, with a visible "not yet translated" indicator. Content should never silently disappear from a listing just because it lacks a translation.

This is implemented with two different, existing Drupal/contrib mechanisms depending on how the view is built. Neither requires a custom Views filter or Facets processor.

### Search API-backed views and facets

Use the `language_with_fallback` property. It's provided by Search API's `LanguageWithFallback` processor, which is already enabled (locked/hidden) on every Mukurtu index — it just isn't wired to an indexed field yet on most indexes. For each indexed item, this property resolves to the item's own language plus every language that falls back to it, so filtering `language_with_fallback = <current interface language>` returns exactly one item per entity: the translation if one exists, otherwise the fallback (default-language) item.

To use it on an index that doesn't have it yet: add a field with `property_path: language_with_fallback` (no `datasource_id` — it's a processor-derived property, not a datasource field), then filter/facet on that field instead of the raw `search_api_language` property.

### Plain-entity views (no Search API)

Use core's own recipe, the same one `core/modules/media_library` ships:

- A `default_langcode = 1` filter, so each entity contributes exactly one row (its original/default translation) — no duplicate rows per translation.
- The display's `rendering_language` set to `***LANGUAGE_language_content***`.

`EntityViewBuilder` resolves the actual rendered translation via `entity.repository`'s `getTranslationFromContext()`, which already implements "current translation if it exists, else the default" — so this combination gives fallback behavior for free, no custom code.

### Choosing between the two

If the view **sorts or filters on translatable field values** (e.g. alphabetical by title, filtered by a translatable description), it must use the Search API path. The plain-entity `default_langcode = 1` filter selects the *original*-language row, so sorting or filtering on it operates on original-language values even when rendering shows the current translation.

If the view only lists/renders entities without sorting or filtering on translatable text, either mechanism works; prefer whichever the view already uses (Search API vs. plain Views) to avoid an unrelated migration.

### The "not yet translated" indicator

Implemented once, in `mukurtu_core` via `hook_entity_view_alter()`, rather than per content type or per view. It fires whenever the entity being rendered has no translation into the current content language. Don't duplicate this per view or per row template.

## Exemptions

Admin-only views that intentionally list content across all languages for editorial/management purposes (not visitor-facing browse/search) may skip this policy. Document the reason with a comment where the view is defined.

## Checklist for new views/facets

- [ ] Does this view show content to site visitors (not just admins/editors)? If yes, it needs the fallback policy above.
- [ ] Does it sort or filter on translatable field values? If yes, use the Search API path even if a plain-entity view would otherwise be simpler.
- [ ] If it's exempt, is the reason documented at the point of definition?
