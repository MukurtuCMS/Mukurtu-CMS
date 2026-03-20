# Test Coverage & CI Fixes — Code Review Overview
**Branch:** `claude/review-issues-t1PpP` → `main`
**Date:** 2026-03-19
**Repo:** https://github.com/alexmerrill/Mukurtu-CMS

---

## Summary

This session addressed two distinct problems:

1. **A real access-control bug** in `MukurtuProtocolNodeAccessControlHandler` — users could edit content they should not have been able to.
2. **PHPUnit 10 CI noise** — a combination of a test failure, a deprecated schema warning, and 70+ deprecation notices from `@group` doc-comment metadata. These were all suppressed or fixed so CI output is clean.

Once CI was green, kernel test coverage was expanded for three previously untested modules: `mukurtu_digital_heritage`, `mukurtu_media`, `mukurtu_dictionary`, and `mukurtu_local_contexts`.

---

## Commits (chronological)

### 1. Access Control Bug Fix
**Commit:** [`3dc3889b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/3dc3889bfd2bd201cdcb78740f550c0cd2f69db6)
**File:** `modules/mukurtu_protocol/src/MukurtuProtocolNodeAccessControlHandler.php`

**The bug:** Content using the `all` sharing mode (requiring membership in *every* applied protocol) was incorrectly granting edit/delete access to users who held a qualifying role in *any one* of the protocols, not *all* of them.

Concretely: content locked to `[Protocol A (open), Protocol B (open)]` with `all` sharing could be edited by a user who was a contributor in Protocol A but had no role in Protocol B at all.

**The fix:** The access check was restructured so that for `all`-mode content, every protocol in the set must independently satisfy the role requirement. The loop now returns `false` early as soon as it finds a protocol where the user does not hold a qualifying role (contributor + owner, or protocol_steward). The previous logic short-circuited in the wrong direction — a single satisfied protocol was sufficient.

**Why this matters:** This is the core content permission model. A miscalculation here could expose restricted cultural content to users who were intentionally excluded from one of the protocols applied to it.

---

### 2. Test Cleanup: ProtocolEntityAccessTest
**Commit:** [`65edc146`](https://github.com/alexmerrill/Mukurtu-CMS/commit/65edc1463c2a3693a0fba9096f29dd811433f4dd)
**File:** `modules/mukurtu_protocol/tests/src/Kernel/Access/ProtocolEntityAccessTest.php`

Minor cleanup pass on the protocol access test to align test expectations with the corrected behavior from commit `3dc3889b`. No logic changes — test assertion alignment only.

---

### 3. New: Digital Heritage Kernel Tests
**Commits:** [`2302d328`](https://github.com/alexmerrill/Mukurtu-CMS/commit/2302d3281854bb402ec93c71745cbf501e8be04d) + [`dcd711d3`](https://github.com/alexmerrill/Mukurtu-CMS/commit/dcd711d3f656963a4468f4258bd2980bb61481a8)
**Files added:**
- `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTestBase.php`
- `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageEntityTest.php`
- `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTaxonomyTest.php`
- `phpunit.xml` — added `mukurtu_digital_heritage` kernel test directory

**`DigitalHeritageTestBase`** sets up the full environment required for DH tests: ~20 modules, all 6 Local Contexts database tables, community + open protocol + OG roles, and two helpers (`createCategory()` and `buildDigitalHeritage()`).

**`DigitalHeritageEntityTest`** (5 tests):
- Bundle class and interface checks (`DigitalHeritage` implements `CulturalProtocolControlledInterface`, `PeopleInterface`, `MukurtuDraftInterface`)
- Required vs optional field assertions (`title` and `field_category` are required)
- Field cardinality (8 multi-value descriptor fields at -1, 5 single-value fields at 1)
- `auto_create` settings (`field_category` is manager-controlled and must be FALSE; 9 descriptor fields must be TRUE)
- Protocol field round-trip and category-required validation violation

**`DigitalHeritageTaxonomyTest`** (6 tests):
- Taxonomy term auto-create on save
- Multi-value term ordering preservation
- Term reuse by ID across entities (no duplicates)
- Multiple creators (auto-create, unlimited cardinality)
- Related content cross-reference between two DH nodes
- Protocol access smoke test on the real entity class (strict protocol: outsider denied, member allowed)

---

### 4. PHPUnit 10 Fixes — Listener Revert + @group → Attributes
**Commits:** [`3a6367f6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/3a6367f6c6e15fe0689a983ac01ab9e8774d4d5b) + [`ab253924`](https://github.com/alexmerrill/Mukurtu-CMS/commit/ab2539247da1afadca36b98861f1767f3f629e9c)
**Files changed:** `phpunit.xml`, `DigitalHeritageEntityTest.php`, `DigitalHeritageTaxonomyTest.php`, `MukurtuDraftsEntityTest.php`

Two separate problems that were addressed together then cleaned up:

**Problem A — `assertContains` type mismatch in `testProtocolFieldPersistence`:**
`getProtocols()` returns `int[]` (cast via `array_map` in `CulturalProtocolItem::unformatProtocols()`), but `entity->id()` returns `string`. PHPUnit 10 uses strict type comparison in `assertContains`, so `assertContains('1', [1])` fails. Fixed by casting: `assertContains((int) $this->protocol->id(), $loaded->getProtocols())`.

**Problem B — attempted `<listeners>` → `<extensions>` migration:**
An attempt was made to migrate `phpunit.xml` to the PHPUnit 10 `<extensions>` API. This caused `Cannot bootstrap extension because class \Drupal\Tests\Listeners\DrupalListener does not exist` because `DrupalListener` implements the PHPUnit 9 listener interface, not `PHPUnit\Runner\Extension\Extension`. The change was reverted — `<listeners>` continues to work and only emits a deprecation notice, not a failure.

**`@group` → PHP 8 attribute conversion** (for `mukurtu_digital_heritage` and `mukurtu_drafts`):
PHPUnit 10 deprecated `@group` in doc-comments. Replaced with `#[\PHPUnit\Framework\Attributes\Group('...')]` on `DigitalHeritageEntityTest`, `DigitalHeritageTaxonomyTest`, and `MukurtuDraftsEntityTest`. The `@group` line is also removed from the docblock entirely (leaving it alongside the attribute still triggers the warning).

---

### 5. PHPUnit 10 Fixes — Deprecated Schema + Remaining @group Conversions
**Commit:** [`12b71fca`](https://github.com/alexmerrill/Mukurtu-CMS/commit/12b71fca19e1a34a2b4560e2354785ba351519f5)
**Files changed:** `phpunit.xml`, 5 test class files

**Problem — deprecated schema warning (Warning 1 in CI):**
PHPUnit 10 requires an explicit `xsi:noNamespaceSchemaLocation` pointing to the bundled XSD to validate against the current schema. Added to `phpunit.xml`:
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         ...>
```
The path is relative to the project root, which is where CI runs PHPUnit after `composer install`.

**Remaining `@group` conversions (Warnings 2–56):**
The same `@group` → `#[\PHPUnit\Framework\Attributes\Group(...)]` conversion applied to the remaining 5 test classes that had not yet been updated:
- `AccessByProtocolTest`
- `CommunityEntityAccessTest`
- `ProtocolEntityAccessTest`
- `CollectionEntityTest`
- `PersonalCollectionEntityAccessTest`

---

### 6. New: Mukurtu Media Kernel Tests
**Commit:** [`840fb318`](https://github.com/alexmerrill/Mukurtu-CMS/commit/840fb31833eb54b3afaa7d463a805e1d79caeee5)
**Files added:**
- `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTestBase.php`
- `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaEntityTest.php`
- `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTaxonomyTest.php`
- `phpunit.xml` — added `mukurtu_media` kernel test directory

**Key design decision in `MukurtuMediaTestBase`:** All three media types (`audio`, `image`, `document`) are registered using the `file` media source plugin backed by a shared `field_media_test_source` field. This sidesteps a conflict: Drupal's `MediaType::save()` auto-creates a source field per bundle, which would collide with Mukurtu's `hook_entity_field_storage_info` that already provides `field_media_audio_file`, `field_media_image`, etc. as programmatic bundle fields. The test source field is entirely separate; the Mukurtu bundle fields and bundle classes are still fully active.

**`MukurtuMediaEntityTest`** (11 tests):
- Bundle class and interface checks per bundle (`Audio`/`Image`/`Document` each have a distinct interface set; `Document` additionally implements `MukurtuThumbnailGenerationInterface`)
- Required vs optional field assertions per bundle (`field_media_audio_file`, `field_media_document`, `field_media_image` are required on their respective bundles)
- Field cardinality (`field_media_tags`, `field_contributor`, `field_people` are unlimited; source file and transcription are single-value)
- `auto_create=TRUE` on all taxonomy reference fields (`field_media_tags`, `field_contributor`, `field_people`) across all bundles
- Protocol field round-trip (sharing setting and protocol ID)
- `ImageAltRequired` constraint fires when `target_id` is set on `field_media_image` but `alt` is empty
- `ImageAltRequired` constraint passes when alt text is provided

**`MukurtuMediaTaxonomyTest`** (8 tests):
- `field_media_tags` auto-create on save
- Multiple tags preserved in order
- Tag term reuse by ID across different bundles (no duplication)
- Multiple contributors (`field_contributor`, auto-create, unlimited)
- `field_people` auto-create on both `audio` and `document`
- Strict protocol access smoke test (outsider denied, protocol steward allowed)
- `field_identifier` persists on all three bundle types

---

### 7. New: Dictionary & Local Contexts Kernel Tests
**Commit:** [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108dae5092b84131ea5a732813e49ab89c9a)
**Files added:**
- `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php`
- `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryEntityTest.php`
- `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryWordListTest.php`
- `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsTestBase.php`
- `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsSupportedProjectTest.php`
- `phpunit.xml` — added `mukurtu_dictionary` and `mukurtu_local_contexts` kernel test directories

#### Dictionary Tests

**`DictionaryTestBase`** is the heaviest base class in the suite — the `mukurtu_dictionary` module has roughly 30 transitive dependencies (blazy, entity_browser, facets, search_api, mukurtu_browse, mukurtu_local_contexts, etc.). The base installs only the schemas and configs actually exercised by entity tests, not the full module config. It also installs all 6 Local Contexts tables because `DictionaryWord::bundleFieldDefinitions()` declares `local_contexts_project` and `local_contexts_label_and_notice` field types, which require those tables at entity schema install time. Creates NodeType bundles for `dictionary_word` and `word_list` and ParagraphsType bundles for `dictionary_word_entry` and `sample_sentence` — this is what triggers `hook_entity_bundle_info_alter` to assign the custom bundle classes. Creates the `language_community` entity schema. Provides `buildDictionaryWord()` and `buildWordList()` helpers.

**`DictionaryEntityTest`** (11 tests):
- Bundle class and interface checks for all four bundles: `DictionaryWord` (implements `DictionaryWordInterface`, `CulturalProtocolControlledInterface`, `BundleSpecificCheckCreateAccessInterface`, `MukurtuDraftInterface`), `WordList`, `DictionaryWordEntry`, `SampleSentence`
- Required vs optional fields (`field_dictionary_word_language` is the only required custom field)
- Field cardinality (single-value: language, alternate_spelling, glossary_entry, definition, source; multi-value: keywords, contributor, translation, word_type, recording, sample_sentences, additional_word_entries)
- `preSave` auto-fill of `field_glossary_entry` from the first character of the title, including correct multi-byte (UTF-8) handling via `mb_substr`
- `preSave` does not overwrite a manually-set `field_glossary_entry`
- `bundleCheckCreateAccess` returns `allowed` when at least one `language` vocabulary term exists, `forbidden` when none exist
- `auto_create` settings: `field_keywords` and `field_word_type` are TRUE; `field_dictionary_word_language` must be FALSE (language terms are manager-controlled, not auto-created)
- Protocol field round-trip and language field reference persistence

**`DictionaryWordListTest`** (8 tests):
- `add()` increases count and the word appears after a save/reload cycle
- Multiple `add()` calls preserve insertion order
- `remove()` removes the correct word and leaves others intact
- `remove()` on a word not in the list is a no-op
- Removing all words results in a count of zero
- `getCount()` reflects in-memory state before save
- `add()` does not deduplicate — calling it twice with the same word adds two entries (this documents the current behavior, which is intentional: the UI enforces uniqueness)
- `postSave()` completes without error when referenced words are present (cache invalidation smoke test)

#### Local Contexts Tests

**`LocalContextsTestBase`** is intentionally minimal: only `mukurtu_local_contexts` and its single declared dependency (`mukurtu_protocol`) are loaded, along with OG and user infrastructure. The 6 Local Contexts tables are installed via `installSchema`. A `insertProjectRecord()` helper is provided because `getSiteSupportedProjects()`, `getGroupSupportedProjects()`, and `getAllProjects()` all JOIN `supported_projects` → `projects` — tests that call those methods need a matching row in `mukurtu_local_contexts_projects` first.

**`LocalContextsSupportedProjectTest`** (13 tests):
- `addSiteProject` / `isSiteSupportedProject` basic CRUD
- `addSiteProject` idempotency (calling twice must not throw a primary-key violation)
- `isSiteSupportedProject` returns false for unknown project IDs
- `addGroupProject` / `isGroupSupportedProject` CRUD and idempotency for community group
- Scope isolation: a site-scoped project is not detected as a group project
- `getSiteSupportedProjects` returns site projects and excludes group projects
- `getGroupSupportedProjects` returns the correct group's projects and is isolated per group
- `removeSiteProject` removes the scope entry and cascades to delete the project record when no other references remain
- `removeSiteProject` preserves the project record when a group scope still references it
- `removeGroupProject` removes and cascades correctly when unused
- `removeProject(force=TRUE)` deletes regardless of remaining references
- `removeProject(force=FALSE)` is a no-op when the project is still referenced
- `getSiteSupportedProjects(exclude_legacy=TRUE)` filters out `default_tk` and `sitewide_tk` legacy IDs

---

## Files Changed — Full Index

| File | Type | Commit |
|------|------|--------|
| `modules/mukurtu_protocol/src/MukurtuProtocolNodeAccessControlHandler.php` | Bug fix | `3dc3889b` |
| `modules/mukurtu_protocol/tests/src/Kernel/Access/ProtocolEntityAccessTest.php` | Test cleanup | `65edc146` |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTestBase.php` | New | `2302d328` + `dcd711d3` |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageEntityTest.php` | New | `2302d328` + `3a6367f6` + `ab253924` |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTaxonomyTest.php` | New | `2302d328` + `ab253924` |
| `modules/mukurtu_drafts/tests/src/Kernel/MukurtuDraftsEntityTest.php` | @group → attribute | `ab253924` |
| `modules/mukurtu_collection/tests/src/Kernel/Access/CollectionEntityTest.php` | @group → attribute | `12b71fca` |
| `modules/mukurtu_collection/tests/src/Kernel/Access/PersonalCollectionEntityAccessTest.php` | @group → attribute | `12b71fca` |
| `modules/mukurtu_protocol/tests/src/Kernel/Access/AccessByProtocolTest.php` | @group → attribute | `12b71fca` |
| `modules/mukurtu_protocol/tests/src/Kernel/Access/CommunityEntityAccessTest.php` | @group → attribute | `12b71fca` |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTestBase.php` | New | `840fb318` |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaEntityTest.php` | New | `840fb318` |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTaxonomyTest.php` | New | `840fb318` |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php` | New | `bc26108d` |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryEntityTest.php` | New | `bc26108d` |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryWordListTest.php` | New | `bc26108d` |
| `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsTestBase.php` | New | `bc26108d` |
| `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsSupportedProjectTest.php` | New | `bc26108d` |
| `phpunit.xml` | Config | `2302d328`, `3a6367f6`, `ab253924`, `12b71fca`, `840fb318`, `bc26108d` |

---

## What Was Not Changed

- No production code was changed for any of the test additions — only tests and `phpunit.xml`.
- The `<listeners>` block in `phpunit.xml` was **intentionally kept** (not migrated to `<extensions>`). `DrupalListener` does not implement `PHPUnit\Runner\Extension\Extension` and cannot be used with the `<extensions>` API. It will continue to emit a single PHPUnit deprecation notice per run until Drupal core updates the class.
- No existing tests were deleted or weakened.

---

## Points Worth Reviewing

1. **`MukurtuProtocolNodeAccessControlHandler.php` (`3dc3889b`)** — this is the only change to production code and deserves the most attention. The original logic and the corrected logic are structurally similar; the difference is in how the inner loop over protocols short-circuits for the `all` sharing mode. Verify that the `any` mode path is unaffected (it should be — the `any`/`all` branches are independent).

2. **`MukurtuMediaTestBase` — shared source field workaround** — the `field_media_test_source` approach is a testing-only workaround. If Mukurtu ever moves away from `hook_entity_field_storage_info` for media bundle fields and instead uses config YAML, this workaround would become unnecessary. The comment in the file explains the reason.

3. **`DictionaryTestBase` — heavy module list** — the dictionary base loads ~30 modules. If the suite becomes slow, consider whether some of the lower-level dependencies (blazy, entity_browser, facets) can be shimmed out. At present they are declared in `$modules` but none of their config is installed, so the overhead is just class loading.

4. **`LocalContextsTestBase::insertProjectRecord()` helper** — this inserts directly into the database to satisfy the JOIN in `getSiteSupportedProjects()`. An alternative would be to test only the methods that don't require the join (e.g., just `addSiteProject`/`isSiteSupportedProject` which query `supported_projects` directly). The current approach is more realistic but does couple the test setup to the database schema.

5. **`DictionaryWordListTest::testAddSameWordTwiceResultsInDuplicate`** — this test documents that `add()` is a pure push with no deduplication. It is asserting the current behavior, not a desired invariant. If the intent is that a word should appear at most once in a list, this test should be changed to assert count=1 and `add()` should get a guard. Worth confirming with the team which behavior is correct.
