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

## Known contrib limitation: locale_string_is_safe() false positives (#1901)

Drupal core's `locale_string_is_safe()` (`core/includes/locale.inc`) runs
every translatable string through an HTML allowlist and rejects any that
contains markup it can't verify as safe. It has no hook, no allowed-tags
setting, and no alter, so it can't be reconfigured or bypassed from a
module without patching core.

It fires in two places:

- **config-schema scanning**, when a `translatable`-typed schema leaf
  wraps a machine token in markup (e.g. a hidden `<div>`), and
- **`.po` file import**, when adding a language, running
  `drush locale:update`, or the cron translation-update job pulls a
  contrib module's interface translations.

The `.po`-import case produces the admin warning "N translation strings
were skipped because of disallowed or malformed HTML" plus a `locale`
dblog entry naming the files. It is admin-only, shown once per language
import, and never seen by site visitors; the only user-visible effect is
that those specific strings render untranslated for that language.

Two modules Mukurtu ships (via `mukurtu_bot_protection`) trip this on
import. Both are genuine upstream false positives, tracked upstream,
neither fixable in this profile:

- **Klaro** - the privacy-policy URL config string `internal:/<front>`
  (`<front>` reads as an unknown HTML tag). drupal.org/project/klaro
  issue #3538882 (open at time of writing).
- **CAPTCHA** (`image_captcha` submodule) - a font-preview string
  embedding `<img src="@font_preview_url" alt="@title" title="@title">`.
  drupal.org/project/captcha issue #3398914 (open at time of writing).

Do not suppress the warning by intercepting the messenger/logger, or by
removing these modules from the translation-update project list via
`hook_locale_translation_projects_alter()`: the first hides a real signal
for every future import in every language, the second drops all
translations for those modules in all languages. Track the upstream
issues instead.

To see which strings were skipped on a given site, run
`drush watchdog:show --type=locale`, or open Reports > Recent log
messages filtered to type "locale".

When the same false positive shows up in **Mukurtu-owned** config
instead, it usually means a schema type is marked `translatable` for
content that was never meant to be translated (e.g. a machine-readable
token, not visible text). See #1638 for a real instance and its fix:
`mukurtu_multilingual/src/Hook/ViewsSchemaHooks.php` overrides the
offending schema type via `hook_config_schema_info_alter()` rather than
patching contrib or silencing the check.

## Checklist for new views/facets

- [ ] Does this view show content to site visitors (not just admins/editors)? If yes, it needs the fallback policy above.
- [ ] Does it sort or filter on translatable field values? If yes, use the Search API path even if a plain-entity view would otherwise be simpler.
- [ ] If it's exempt, is the reason documented in `ViewLanguageFallbackCoverageTest::EXEMPTIONS`?
