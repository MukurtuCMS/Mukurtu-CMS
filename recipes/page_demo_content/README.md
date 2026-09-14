# Basic page demo content

A Drupal recipe that creates one fully-described **Basic page** item so the
accessibility program has a real, rendered page to test on Tugboat previews
or local builds.

Basic page is the simplest of Mukurtu's content types: it has no cultural
protocol, community, or taxonomy fields — just `body` and
`field_page_media_assets`. This recipe declares `accessibility_demo_content`
as a prerequisite (see `recipe.yml`'s `recipes:` key) purely so it can reuse
that recipe's image/audio/document media rather than creating a third set of
placeholder files; there's no ID-assumption caveat to repeat here since Basic
page doesn't reference a Community or Protocol at all.

## Running it

After a fresh `drush site-install mukurtu` (or on Tugboat, after the site is
built):

```
drush recipe web/profiles/mukurtu/recipes/page_demo_content
drush cr
```

Find the page at `/admin/content`, titled "Sample Basic Page (Accessibility
Demo)", or its pathauto-generated alias.

## What it creates

- **1 Basic page node**, with `body` containing a heading, a paragraph, a
  list, and an external link (for testing rich-text accessibility), and
  `field_page_media_assets` reusing the image/audio/document media from
  `accessibility_demo_content`.

## Fields intentionally left empty

- `field_representative_media`, `field_all_related_content`,
  `field_citation`, `field_multipage_page_of`, `field_communities`, and
  `field_in_collection` are computed fields on the Basic page bundle and
  can't be set directly.
