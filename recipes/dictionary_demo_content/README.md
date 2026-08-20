# Dictionary demo content

A Drupal recipe that creates one fully-described **Word List** containing
one fully-described **Dictionary Word**, so the accessibility program has
real, rendered dictionary pages to test on Tugboat previews or local builds.

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
drush recipe web/profiles/mukurtu/recipes/dictionary_demo_content
drush cr
```

Find both nodes at `/admin/content` ("Sample Word List (Accessibility Demo)"
and "Sample Word (Accessibility Demo)"), or their pathauto-generated
aliases.

## What it creates

- **1 taxonomy term**: a Word Type ("Noun") for `field_word_type`.
- **3 paragraphs**: two Sample Sentence entries (a paragraph belongs to a
  single parent, so the nested entry below needs its own copy rather than
  reusing the Dictionary Word's) and one Additional Word Entry — the
  dictionary module's way of recording an alternate-community spelling,
  pronunciation, and definition for the same word, nested with its own
  Sample Sentence.
- **1 Dictionary Word node** ("Sample Word"), with essentially every field
  populated: language, alternate spelling, glossary entry, keywords,
  location, contributor, definition, pronunciation, an audio recording, a
  thumbnail and media assets (all reusing media from
  `accessibility_demo_content`), a sample sentence, the nested additional
  word entry, source, translation, word origin, word type, related content,
  and coverage/coverage description.
- **1 Word List node** ("Sample Word List") containing the Dictionary Word
  above in `field_words`, plus a description, summary, source, keywords,
  location, related content, image, and coverage/coverage description.

## Fields intentionally left empty

- `field_local_contexts_projects` / `field_local_contexts_labels_and_notices`
  — same reason as in `accessibility_demo_content`: these pull live data from
  the Local Contexts Hub API, unavailable offline/in CI.
- `field_representative_media`, `field_all_related_content`,
  `field_citation`, `field_multipage_page_of`, `field_communities`,
  `field_in_collection`, and `field_in_word_list` are computed fields on
  these bundles and can't be set directly.
