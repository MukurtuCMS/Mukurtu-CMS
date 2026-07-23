# Place demo content

A Drupal recipe that creates one fully-described **Place** item so the
accessibility program has a real, rendered Place page (including a
populated map field) to test on Tugboat previews or local builds.

This recipe declares `accessibility_demo_content` as a prerequisite (see
`recipe.yml`'s `recipes:` key), so running this recipe alone also applies
that one first. It reuses that recipe's Community/Protocol and some of its
taxonomy terms/media rather than creating near-duplicates — **all of the
same assumed-ID caveats documented in
`../accessibility_demo_content/README.md` apply here too** (Protocol ID 1,
Community ID 1, node ID 1 for the default landing page, etc.).

## Running it

After a fresh `drush site-install mukurtu` (or on Tugboat, after the site is
built):

```
drush recipe web/profiles/mukurtu/recipes/place_demo_content
drush cr
```

Find the Place at `/admin/content`, titled "Sample Place (Accessibility
Demo)", or its pathauto-generated alias.

## What it creates

- **1 image file + media** ("Sample Landscape Photograph"), with descriptive
  alt text, generated the same way as the other recipes' images but distinct
  from them — a place's primary media should reasonably be a landscape/
  location photo rather than a reused object or portrait photo.
- **1 taxonomy term**: a Place Type ("Sample Place Type") for
  `field_place_type` (a vocabulary not used by any prior recipe).
- **1 paragraph**: a Text Section with Title ("History") for `field_sections`
  — the Place bundle's equivalent of the Person bundle's Formatted Text with
  Title, just a differently-named paragraph bundle with the same
  title+body shape.
- **1 Place node**, with `field_media_assets` (the new landscape image plus
  the audio/document media reused from `accessibility_demo_content`),
  `field_keywords`, `field_location` and `field_other_place_names` (reusing
  the Location term from `accessibility_demo_content`), `field_sections`,
  `field_related_content` (the Sample Related Story article), and
  `field_coverage`/`field_coverage_description`.

## Fields intentionally left empty

- `field_local_contexts_projects` / `field_local_contexts_labels_and_notices`
  — same reason as in `accessibility_demo_content`: these pull live data from
  the Local Contexts Hub API, unavailable offline/in CI.
- `field_representative_media`, `field_all_related_content`,
  `field_citation`, `field_multipage_page_of`, `field_communities`, and
  `field_in_collection` are computed fields on the Place bundle and can't be
  set directly.
