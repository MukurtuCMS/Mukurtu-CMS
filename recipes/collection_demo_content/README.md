# Collection demo content

A Drupal recipe that creates one fully-described **Collection** item so the
accessibility program has a real, rendered Collection page to test on
Tugboat previews or local builds.

This recipe declares `accessibility_demo_content` as a prerequisite (see
`recipe.yml`'s `recipes:` key), so running this recipe alone also applies
that one first. It reuses that recipe's Community/Protocol and one of its
taxonomy terms rather than creating a second set — **all of the same
assumed-ID caveats documented in
`../accessibility_demo_content/README.md` apply here too** (Protocol ID 1,
Community ID 1, node ID 1 for the default landing page, etc.).

## Running it

After a fresh `drush site-install mukurtu` (or on Tugboat, after the site is
built):

```
drush recipe web/profiles/mukurtu/recipes/collection_demo_content
drush cr
```

Find the Collection at `/admin/content`, titled "Sample Collection
(Accessibility Demo)", or its pathauto-generated alias (something like
`/collection/sample-collection-accessibility-demo` — pathauto overrides the
alias set in content, the same as it does for the Digital Heritage item).

## What it creates

- **1 Collection node**, with:
  - `field_collection_image` pointing at the Digital Heritage recipe's image
    media.
  - `field_items_in_collection` containing that recipe's Digital Heritage
    item — the only node of an allowed bundle
    (`digital_heritage`/`dictionary_word`/`word_list`/`person`/`place`) that
    exists yet. As Person, Place, Word List, and Dictionary Word demo content
    get added in later recipes, this collection is a natural place to add
    them as additional members.
  - `field_related_content` pointing at the Sample Related Story article.
  - `field_keywords` and `field_location` reusing terms from the Digital
    Heritage recipe rather than creating near-duplicates.
  - `field_description`/`field_coverage_description`/`field_source`/
    `field_summary`/`field_coverage` populated the same way as the Digital
    Heritage item.

## Fields intentionally left empty

- `field_child_collections` — sub-collections. Would need a second Collection
  node to point at; not created here to keep this recipe self-contained. Add
  one manually if the review needs to cover nested collections.
- `field_local_contexts_projects` / `field_local_contexts_labels_and_notices`
  — same reason as in `accessibility_demo_content`: these pull live data from
  the Local Contexts Hub API, unavailable offline/in CI.
- `field_representative_media`, `field_citation`, `field_communities`,
  `field_multipage_page_of`, `field_parent_collection`,
  `field_all_related_content`, and `field_in_collection` are computed
  fields on the Collection bundle (unlike on Digital Heritage, where most of
  these are real, settable fields) — they can't be set directly.
