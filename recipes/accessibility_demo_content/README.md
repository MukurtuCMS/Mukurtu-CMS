# Accessibility demo content

A Drupal recipe that creates one fully-described **Digital Heritage** item —
plus every supporting entity it needs (a Community, a Protocol, 12 taxonomy
terms, three media items with real files, a knowledge-keeper paragraph, and
one related Article) — so the accessibility program has real, rendered pages
to test on Tugboat previews or local builds, instead of an empty site.

## Running it

After a fresh `drush site-install mukurtu` (or on Tugboat, after the site is
built):

```
drush recipe web/profiles/mukurtu/recipes/accessibility_demo_content
drush cr
```

The Digital Heritage node's title is "Woven Basket, Maker Unknown (Sample
Item)" — find it at `/admin/content`, or its URL alias, which pathauto
generates automatically (something like
`/digital-heritage/woven-basket-maker-unknown-sample-item`).

Verified end-to-end (recipe apply + full page render with no errors) against
a throwaway, freshly-installed site; see "Assumptions and limitations" below
for what that testing surfaced.

## What it creates

- **Community**: "Sample Community (Accessibility Demo)"
- **Protocol**: "Public Access (Accessibility Demo)" — access mode `open`, so
  the demo content is visible to anonymous visitors without logging in.
- **An OG membership** granting the site's admin account (uid 1) the
  Community Manager role on the Sample Community — see below for why this is
  needed.
- **12 taxonomy terms**, one each for Category, Contributor, Creator, Format,
  Language, Location, People, Publisher, Subject, Type, and two Keywords.
- **3 media items**, each with a real (generated placeholder) file so the
  page actually renders media instead of broken links:
  - An image with descriptive **alt text** filled in.
  - An audio file (a generated tone) with a transcript in `field_transcription`.
  - A PDF document with extracted text in `field_extracted_text`.
- **1 paragraph** (Indigenous Knowledge Keeper) with every field filled in.
- **1 fully-described Article node** ("Sample Related Story"), with a body
  containing a heading, a list, and a link (useful for testing rich-text
  accessibility), plus `field_article_category`, `field_article_keywords`,
  and `field_article_image` (with alt text) — all reusing terms/files created
  above rather than duplicating them. Also used as the "Related content"
  target for every other recipe in this directory (Digital Heritage,
  Collection, Person, Place, Word List/Dictionary Word), since it needs to
  exist before any of them.
- **1 Digital Heritage node** with essentially every field populated (see
  "Fields intentionally left empty" below for the exceptions).

All text content is clearly labeled as placeholder/sample data (titles,
descriptions, and the paragraph's "Sample Knowledge Keeper" fields are
fictional) rather than invented Indigenous cultural or traditional-knowledge
content, since this is demo/test data rather than a real collection record.

## Assumptions and limitations

**This recipe is meant to be run once, on a freshly installed site.** It
makes a few simplifying assumptions that hold true on a fresh install, but
that you should double check if you're applying it to a site that already has
content:

- `field_communities` on the Digital Heritage node and the Article's "related
  content" concept both assume the profile's default landing page (created by
  `mukurtu_install()`) is node ID `1`, which is always true on a fresh
  install since it's the first node created.
- `field_cultural_protocols.protocols` on the node and on each media item is
  set to `'|1|'` (i.e. Protocol ID 1). The `cultural_protocol` field type
  stores raw protocol IDs as a delimited string rather than a real entity
  reference, so Drupal recipes' UUID-to-ID dependency resolution doesn't apply
  to it — there's no declarative way to reference "the Protocol this recipe
  just created" other than assuming its ID. On a fresh install this recipe's
  own "Public Access (Accessibility Demo)" Protocol will be the first one
  created, so it gets ID 1. **If your site already has Communities or
  Protocols, edit `protocols: '|1|'` in `content/node/*.yml` and
  `content/media/*.yml` to the correct ID after checking
  `/admin/mukurtu-protocol/protocol` — otherwise the demo content will
  silently attach itself to whatever protocol already has ID 1.** The same
  applies to `content/og_membership/*.yml`'s `entity_id: '1'`, which assumes
  the Sample Community is Community ID 1.
- This recipe turns off `og.settings:auto_add_group_owner_membership` (a
  config action in `recipe.yml`) and separately creates an `og_membership`
  content entity granting Community Manager. Organic Groups normally
  auto-subscribes a new group's owner as a plain member the instant a
  Community is saved, and a Protocol can't be created unless its creator
  already holds Community Manager on the parent Community (see
  `ProtocolCommunitySelection`) — the real "Add community" admin form works
  around this with a form-submit step that recipes can't replicate
  declaratively, so this disables the auto-subscribe behavior just long
  enough to create the membership with the right role directly. This is a
  global site setting; it stays off after the recipe finishes.
- Discovered while testing this recipe against a genuinely fresh install (not
  caused by the recipe, and not worked around here beyond what's needed to
  unblock it): `config/install/mukurtu_protocol.community_organization.yml`
  ships with a placeholder entry claiming Community ID 1 is already
  organized, even before any Community exists. That makes the very first
  Community's computed `field_child_communities` field fail validation
  against a nonexistent entity — **through the normal "Add community" UI form
  too, not just this recipe.** A config action here clears that config before
  creating content, which is safe on the fresh install this recipe assumes,
  but isn't something to run against a site that already has real community
  hierarchy data. Worth a proper fix in `mukurtu_protocol` itself.
- `field_external_links` only uses external (`https://`) URLs. `internal:`
  and `entity:` URIs reliably fail
  `LinkNotExistingInternalConstraint`'s route-generation check specifically
  while running inside `drush recipe` — the identical URI resolves fine both
  before and after, e.g. in a plain `drush php:script` run against the same
  site state — which points to some transient routing/container state in that
  command's execution context rather than anything about the URI itself.
  Also worth investigating separately; not worked around here since the field
  doesn't need an internal link to be useful for accessibility testing.

Fields intentionally left empty:

- `field_local_contexts_projects` / `field_local_contexts_labels_and_notices`
  — these widgets pull live data from the Local Contexts Hub API, which isn't
  available offline/in CI. Populate these manually through the UI if you need
  to test that specific widget.
- External-embed media bundles (`external_embed`, `soundcloud`) and the local
  `video` bundle (an uploaded `.mp4`/`.webm`/`.ogv`) are not created, since
  this environment has no video encoder to produce a valid sample file and we
  didn't want to guess at an external embed URL. Add one manually if the
  accessibility review needs to cover video playback.
- `field_all_related_content`, `field_in_collection`, and
  `field_multipage_page_of` are computed/read-only fields (derived from other
  content's relationships) and can't be set directly.
- `field_mukurtu_original_record` is a system-managed field used for
  cross-community record copies; it isn't meant to be set by hand.
