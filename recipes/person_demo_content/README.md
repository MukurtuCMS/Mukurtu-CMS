# Person demo content

A Drupal recipe that creates one fully-described **Person** item — including
a real People content-warning overlay — so the accessibility program has a
real, rendered Person page to test on Tugboat previews or local builds.

This recipe declares `accessibility_demo_content` as a prerequisite (see
`recipe.yml`'s `recipes:` key), so running this recipe alone also applies
that one first. It reuses that recipe's Community/Protocol and several of
its taxonomy terms/media rather than creating near-duplicates — **all of the
same assumed-ID caveats documented in
`../accessibility_demo_content/README.md` apply here too** (Protocol ID 1,
Community ID 1, node ID 1 for the default landing page, etc.).

## Running it

After a fresh `drush site-install mukurtu` (or on Tugboat, after the site is
built):

```
drush recipe web/profiles/mukurtu/recipes/person_demo_content
drush cr
```

Find the Person at `/admin/content`, titled "Sample Person (Accessibility
Demo)", or its pathauto-generated alias.

## What it creates

- **1 image file + media** ("Sample Portrait Photograph"), with descriptive
  alt text, generated the same way as the Digital Heritage recipe's image
  but distinct from it, since a person's primary/representative media
  (`field_representative_media`, computed from `field_media_assets`) should
  reasonably be a portrait rather than a reused object photo. Its
  `field_people` matches the "people" term used in the main Person's
  `field_other_names` below, which — combined with that Person being marked
  deceased — makes this media trigger a real **People content warning
  overlay** (`mukurtu_content_warnings`) wherever it renders, a real,
  previously-untested high-priority component
  ([page-inventory.md](../../docs/accessibility/page-inventory.md) #2). This
  recipe enables `people_warnings.enabled` via a config action for that
  reason — see "Config actions" below.
- **2 taxonomy terms**: a Location ("Another Example Location") for
  `field_place_of_death`, distinct from the Location term reused for
  `field_place_of_birth`/`field_location`; and an Interpersonal Relationship
  term ("Colleague") for the related-people paragraph below.
- **2 paragraphs**: a Formatted Text with Title section ("Biography") for
  `field_sections`, and a Related Person entry (pointing at the lightweight
  second Person node, with the "Colleague" relationship type) for
  `field_related_people`.
- **1 lightweight Person node** ("Sample Related Person"), used only as the
  target of the main Person's `field_related_people`/Related Person
  paragraph — the same pattern as the lightweight Article in
  `accessibility_demo_content`.
- **1 main Person node**, deceased (`field_deceased: true`) so that
  `field_date_born`, `field_date_died`, `field_place_of_birth`, and
  `field_place_of_death` are all populated and renderable, plus
  `field_media_assets` (reusing the audio/document media from
  `accessibility_demo_content` alongside the new portrait image),
  `field_keywords`, `field_other_names`, `field_location`, `field_sections`,
  `field_related_people`, `field_related_content`, `field_coverage`, and
  `field_coverage_description`.

## Config actions

- `mukurtu_content_warnings.settings` enables `people_warnings.enabled`
  (`recipe.yml`) so the People warning above actually renders — it's off by
  default. Deliberately *not* using the module's other mechanism
  (`taxonomy_warnings`, a list keyed by numeric taxonomy term ID) instead:
  that would need the same fragile assumed-fresh-install-ID pattern
  documented in `../accessibility_demo_content/README.md`, since a recipe
  has no declarative way to know what ID a term it just created will get.
  The People mechanism is a plain boolean with no ID dependency, so it's
  safe to set unconditionally, and it's also idempotent-safe to re-apply
  every time this recipe runs as a prerequisite of another one — unlike
  `accessibility_demo_content`'s `community_organization` situation (see
  that README), flipping this boolean back to `true` on every run doesn't
  destroy anything.
- Worth knowing if you add a config action like this yourself:
  `simpleConfigUpdate` calls `Config::set($key, $value)` once per top-level
  key you give it, and `Config::set()` replaces a whole branch rather than
  deep-merging it. Setting `people_warnings: {enabled: true}` looks
  reasonable but silently wipes `warning_single`/`warning_multiple`'s
  default text, which then crashes at render time
  (`str_replace(): Argument #3 ($subject) must be of type array|string,
  null given`) the first time a warning actually needs to display. Use dot
  notation for the single leaf key instead: `people_warnings.enabled: true`.

## Fields intentionally left empty

- `field_local_contexts_projects` / `field_local_contexts_labels_and_notices`
  — same reason as in `accessibility_demo_content`: these pull live data from
  the Local Contexts Hub API, unavailable offline/in CI.
- `field_representative_media`, `field_all_related_content`,
  `field_citation`, `field_multipage_page_of`, `field_communities`, and
  `field_in_collection` are computed fields on the Person bundle and can't be
  set directly.
