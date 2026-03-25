# Mukurtu CMS — Test Infrastructure & Coverage
**Repo:** https://github.com/MukurtuCMS/Mukurtu-CMS
**Plain-language summary:** [coverage-plain-language.md](coverage-plain-language.md)
**Last updated:** 2026-03-24

---

## Test Infrastructure

### `MukurtuKernelTestBase` — shared abstract base

**File:** `modules/mukurtu_core/tests/src/Kernel/MukurtuKernelTestBase.php`

All Mukurtu kernel test bases extend this class instead of `KernelTestBase` directly. It owns all shared boilerplate so new test bases start from a clean, minimal delta.

**What the base installs:**
- Schemas: `system/sequences`, `file/file_usage`, `mukurtu_protocol` map+access tables, all 6 `mukurtu_local_contexts` tables
- Entity schemas: `user`, `file`, `taxonomy_term`, `taxonomy_vocabulary`, `og_membership`, `workflow`, `community`, `protocol`
- Config: `filter`, `og`, `system`
- OG group registration, authenticated role (with `access content`), `protocol_steward` OG role
- Fixtures: `$this->currentUser`, `$this->community`, `$this->protocol`
- Blazy workaround: `file.repository` registered as synthetic DI stub; concrete mock set in `setUp()` to satisfy `BlazyFile::__construct()` — required because the `file.repository` service was removed in Drupal 10 but `blazy.file` still declares the dependency

**`getProtocolStewardPermissions(): array`** — override hook for subclasses. Returns the OG permissions to grant the protocol steward role. Default covers group management permissions; subclasses append content-type CRUD permissions via `array_merge(parent::..., [...])`.

**PHP static property note:** A child class that re-declares `$modules` completely replaces the parent's list. Any subclass needing additional modules must redeclare the full list including the parent's modules.

---

### Test base hierarchy

```
KernelTestBase (Drupal core)
└── MukurtuKernelTestBase        mukurtu_core — OG/user/community/protocol setup
    ├── LocalContextsTestBase    mukurtu_local_contexts
    ├── MukurtuMediaTestBase     mukurtu_media
    ├── DictionaryTestBase       mukurtu_dictionary
    ├── PersonTestBase           mukurtu_person
    ├── PlaceTestBase            mukurtu_place
    ├── CommunityRecordTestBase  mukurtu_community_records
    └── CollectionTestBase       mukurtu_collection
        └── MultipageItemTestBase  mukurtu_multipage_items

PHPUnit\Framework\TestCase (no Drupal bootstrap)
└── MukurtuMapNodesParamConverterTest  mukurtu_browse
└── MukurtuBoundingBoxTest             mukurtu_browse
```

`DigitalHeritageTestBase` and `ProtocolAwareEntityTestBase` still extend `KernelTestBase` / `EntityKernelTestBase` directly and are candidates for future refactoring.

---

### Schema installation rules (gotchas)

**Never re-install** what `MukurtuKernelTestBase` already installs (see list above).

| Situation | What to add |
|-----------|-------------|
| Any entity deleted while `layout_builder` is in `$modules` | `installSchema('layout_builder', ['inline_block_usage'])` — `layout_builder_entity_delete()` fires unconditionally for every entity type |
| Any entity deleted while `search_api` is in `$modules` | `installSchema('search_api', ['search_api_item'])` — entity delete hook queries this table. Do NOT install `search_api_task` — it does not exist in this version |
| `$entity->validate()` called on a node | Add `path_alias` to `$modules` + `installEntitySchema('path_alias')` — `PathFieldItemList` calls `path_alias.repository` during validation traversal |
| Module's insert/update hook calls `path_alias.manager` | Same as above (`mukurtu_multipage_items` is a known example) |
| Taxonomy term delete while `comment` is loaded | `installSchema('comment', 'comment_entity_statistics')` + `installEntitySchema('comment')` |
| Media bundle setup | Create `FieldStorageConfig` → `installEntitySchema('media')` → create `MediaType` → create `FieldConfig` per bundle → `clearCachedFieldDefinitions()`. Order matters — `hook_entity_field_storage_info` rebuilds the cache on `MediaType::save()` which can lose newly-created FieldConfigs |
| Asserting protocol identity after save/reload | Use `getProtocolEntities()` (returns entity objects), NOT `getProtocols()` (returns raw `int[]` IDs) |
| Strict `assertContains` comparing entity IDs | `array_keys(loadMultiple())` returns `int[]`; `$entity->id()` returns `string`. PHPUnit 10 strict comparison fails. Use `in_array()` instead |

---

## Coverage by Module

### `mukurtu_protocol`
**Existing tests:** `AccessByProtocolTest`, `CommunityEntityAccessTest`, `ProtocolEntityAccessTest`, `CollectionEntityTest` (access), `PersonalCollectionEntityAccessTest`

**Bug fixed (2026-03-19):** `MukurtuProtocolNodeAccessControlHandler` — `all`-mode content incorrectly granted edit/delete when user held a qualifying role in any one protocol instead of all of them. The loop was restructured to fail fast when any protocol in the set doesn't satisfy the role requirement. This is the only production code change across all sessions.

---

### `mukurtu_collection`
**Test base:** `CollectionTestBase` (extends `MukurtuKernelTestBase`)
Adds: node, node_access, geofield, content_moderation, mukurtu_collection, mukurtu_drafts. Creates `collection` and `page` NodeTypes; `keywords` and `location` vocabularies. Helpers: `buildCollection()`, `buildItem()`.

**`CollectionHierarchyTest`** (20 tests): bundle class identity; `getParentCollection()`/`getParentCollectionId()`; `getChildCollectionIds()`; `setChildCollections()`; `removeAsChildCollection()` (verifies DB-reloaded parent); `isRootCollection()`; `getRootCollections()`; `getRootCollectionForCollection()` from root/child/grandchild; `getCollectionHierarchy()` depth/children/max_depth truncation; `getCollectionFromNode()` collection vs non-collection node.

---

### `mukurtu_digital_heritage`
**Test base:** `DigitalHeritageTestBase` (extends `KernelTestBase` directly — candidate for refactoring)

**`DigitalHeritageEntityTest`** (5 tests): bundle class + interfaces (`CulturalProtocolControlledInterface`, `PeopleInterface`, `MukurtuDraftInterface`); required fields (`title`, `field_category`); cardinality (8 multi-value, 5 single-value); `auto_create` settings (`field_category` FALSE — manager controlled; descriptor fields TRUE); protocol round-trip + category-required violation.

**`DigitalHeritageTaxonomyTest`** (6 tests): term auto-create on save; multi-value ordering; term reuse (no duplication); multiple creators; related content cross-reference; protocol access smoke test.

---

### `mukurtu_media`
**Test base:** `MukurtuMediaTestBase` (extends `MukurtuKernelTestBase`)

**Design decision:** All three bundles (`audio`, `image`, `document`) use the `file` media source plugin backed by a shared `field_media_test_source` field. `MediaType::save()` would auto-create a source field that collides with Mukurtu's `hook_entity_field_storage_info`-registered bundle fields. The test source field is entirely separate; the real Mukurtu bundle fields and bundle classes remain active.

**`MukurtuMediaEntityTest`** (11 tests): bundle class + interface checks per bundle (`Document` additionally implements `MukurtuThumbnailGenerationInterface`); required fields per bundle; cardinality; `auto_create=TRUE` on all taxonomy reference fields; protocol round-trip; `ImageAltRequired` constraint (fires without alt, passes with alt).

**`MukurtuMediaTaxonomyTest`** (8 tests): tag auto-create; multiple tags in order; tag reuse across bundles; multiple contributors; `field_people` on audio and document; protocol access smoke test; `field_identifier` persistence across bundle types.

---

### `mukurtu_dictionary`
**Test base:** `DictionaryTestBase` (extends `MukurtuKernelTestBase`)
Heaviest base — ~30 transitive dependencies. Installs `search_api_item` and `inline_block_usage` schemas (required when entities are deleted). Creates NodeType bundles for `dictionary_word`/`word_list` and ParagraphsType bundles for `dictionary_word_entry`/`sample_sentence` — this triggers `hook_entity_bundle_info_alter` to assign custom bundle classes. Creates `language_community` entity schema.

**`DictionaryEntityTest`** (11 tests): bundle class + interfaces for all 4 bundles; `field_dictionary_word_language` is the only required custom field; cardinality; `preSave` glossary_entry auto-fill (including multi-byte UTF-8 via `mb_substr`); `preSave` does not overwrite manually-set value; `bundleCheckCreateAccess` allowed/forbidden based on language term existence; `auto_create` (keywords TRUE, language FALSE — manager controlled); protocol + language field persistence.

**`DictionaryWordListTest`** (9 tests): `add()` increases count and survives save/reload; multiple adds preserve order; `remove()` removes correct word, no-op for absent word; removing all words → count 0; `getCount()` reflects in-memory state; `add()` does not deduplicate (documents current behavior — UI enforces uniqueness); `postSave` smoke test (absence of exception is the assertion).

---

### `mukurtu_local_contexts`
**Test base:** `LocalContextsTestBase` (extends `MukurtuKernelTestBase`)
Intentionally minimal. `insertProjectRecord()` helper inserts directly to satisfy the JOIN in `getSiteSupportedProjects()` / `getGroupSupportedProjects()` which JOIN `supported_projects` → `projects`.

**`LocalContextsSupportedProjectTest`** (13 tests): site project CRUD + idempotency; unknown project returns false; group project CRUD + idempotency; scope isolation (site project not visible as group project); `getSiteSupportedProjects` excludes group-scoped; `getGroupSupportedProjects` isolated per group; `removeSiteProject` cascades delete when no other references; preserves project when group reference remains; `removeGroupProject` cascades; `removeProject(force=TRUE)` ignores references; `removeProject(force=FALSE)` no-op when referenced; `exclude_legacy=TRUE` filters `default_tk`/`sitewide_tk`.

---

### `mukurtu_drafts`
**Existing test:** `MukurtuDraftsEntityTest` — basic owner access.

**`MukurtuDraftsBehaviorTest`** (10 tests): `isDraft()` default false; `setDraft()`; `unsetDraft()`; fluent chaining on both; draft and non-draft status persist through save/reload; `mukurtu_drafts_entity_view()` adds `node--unpublished` CSS class for drafts, not for non-drafts; anonymous user forbidden on draft entity, neutral on non-draft.

---

### `mukurtu_multipage_items`
**Test base:** `MultipageItemTestBase` (extends `CollectionTestBase`)
Adds: `path_alias` module + entity schema (module's insert/update hook calls `path_alias.manager`), `multipage_item` entity schema, `mukurtu_multipage_items.settings` config with `bundles_config`. Helper: `buildMultipageItem()`.

**`MultipageItemEntityTest`** (18 tests): entity class + `MultipageItemInterface`; `addPage()`/`getPages()`/`hasPage()`/`getFirstPage()`; `setFirstPage()` — prepend to empty list, prepend new node, move existing to front, already-first is a no-op; `getPages($accessCheck)` filters unpublished when TRUE; `MultipageItemManager::getMultipageEntity()` found/not-found/correct-among-multiples; `MultipageItemManager::isEnabledBundleType()` enabled/disabled/unknown-bundle.

---

### `mukurtu_person`
**Test base:** `PersonTestBase` (extends `MukurtuKernelTestBase`)
Deliberately excludes `mukurtu_browse` and `mukurtu_taxonomy` — neither is called at runtime by the module's hooks; their chains would add ~30 modules. Installs node, paragraph, path_alias, comment schemas. Creates `person` NodeType and `keywords`/`location`/`people` vocabularies.

**`PersonEntityTest`** (17 tests): bundle class + `PersonInterface` + `CulturalProtocolControlledInterface` + `MukurtuDraftInterface`; cardinality for all 13 bundle fields (`field_date_born`/`field_date_died` single, others multi-value); all bundle fields optional; location/birth/death fields target `location` vocabulary; protocol persistence via `getProtocolEntities()`; draft persistence; `field_deceased` defaults FALSE.

---

### `mukurtu_place`
**Test base:** `PlaceTestBase` (extends `MukurtuKernelTestBase`)
Same pattern as `PersonTestBase`. Uses `place_type` vocabulary instead of `people`.

**`PlaceEntityTest`** (14 tests): same groups as Person; `field_place_type` multi-value targeting `place_type` vocabulary; explicit assertion that Place has no `field_date_born` or `field_date_died`.

---

### `mukurtu_community_records`
**Test base:** `CommunityRecordTestBase` (extends `MukurtuKernelTestBase`)
Community records are regular nodes with a `field_mukurtu_original_record` entity reference field — not a custom entity type. Field storage + instance created programmatically via `FieldStorageConfig::create()` / `FieldConfig::create()` (not `installConfig()`) for explicit control. Bundles: `page` (CR-enabled), `basic_page` (no field). Requires `path_alias` for `$entity->validate()`.

**`CommunityRecordFunctionsTest`** (15 tests): `mukurtu_community_records_has_record_field()` with/without field; `mukurtu_community_records_entity_type_supports_records()` enabled/disabled; `mukurtu_community_records_is_community_record()` no-field/empty/set; `mukurtu_community_records_is_original_record()` no-CRs/one-CR/multiple-CRs/non-node-short-circuits; `ValidOriginalRecord` constraint — circular self-reference, target is a CR, entity already has CRs (nesting), valid case → 0 violations. Each failing constraint case asserts `assertCount(1, $violations)` plus `getMessageTemplate()` to confirm the specific constraint fired, not just that any violation occurred.

**Constraint test design:** Tests operate on already-saved entities. The validator runs a `Url::access()` route check only for brand-new entities — skipping that branch keeps tests focused on the field logic without needing a full router.

---

### `mukurtu_browse`
**Approach:** Kernel tests are impractical — `mukurtu_browse` has a ~30-module dependency chain (search_api, leaflet, facets, mukurtu_dictionary, mukurtu_digital_heritage, etc.). The two classes with isolated business logic were targeted with plain PHPUnit unit tests (no Drupal bootstrap). Playwright covers the browse UI end-to-end.

**`MukurtuMapNodesParamConverterTest`** (7 tests, `tests/src/Unit/`): `EntityTypeManagerInterface` is constructor-injected and fully mocked. `convert()` single ID, multiple CSV IDs, result passthrough; `applies()` correct/wrong/missing/empty type.

**`MukurtuBoundingBoxTest`** (10 tests, `tests/src/Unit/`): Class instantiated via `getMockBuilder()->disableOriginalConstructor()->onlyMethods([])->getMock()` to bypass the Views DI chain. Protected `parseBoundingBox()` reached via `ReflectionMethod`; `$this->query` set via `ReflectionProperty` (no `setAccessible(true)` needed in PHP 8.1+). `addCondition()` intercepted via `stdClass` mock with `addMethods()`. Tests: valid 4-value CSV parsed to correct float keys; integers cast to float; too-few/too-many/empty/non-numeric inputs return empty; `query()` triggers exactly 4 conditions for valid bbox, 0 for invalid; correct field names (`centroid_lat`/`centroid_lon`) and operators (`>=`/`<=`) verified via callback.

---

## PHPUnit 10 Migrations

All test classes use `#[\PHPUnit\Framework\Attributes\Group('...')]` instead of `@group` in docblocks. Using both simultaneously still triggers the deprecation warning — the docblock annotation must be removed entirely.

The `<listeners>` block in `phpunit.xml` is **intentionally kept**. `DrupalListener` implements the PHPUnit 9 listener interface, not `PHPUnit\Runner\Extension\Extension`, and cannot be migrated to `<extensions>`. It emits one deprecation notice per run until Drupal core updates the class.

`phpunit.xml` schema declaration added:
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd" ...>
```

---

## All Files Changed

| File | Change | Date | Commit(s) |
|------|--------|------|-----------|
| `modules/mukurtu_protocol/src/MukurtuProtocolNodeAccessControlHandler.php` | Bug fix — `all`-mode access | 2026-03-19 | [`4f2ae15b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/4f2ae15b) [`d50d5491`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d50d5491) [`3dc3889b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/3dc3889b) |
| `modules/mukurtu_protocol/tests/src/Kernel/Access/ProtocolEntityAccessTest.php` | Test assertion alignment | 2026-03-19 | [`3dc3889b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/3dc3889b) |
| `modules/mukurtu_core/tests/src/Kernel/MukurtuKernelTestBase.php` | **Created** — shared abstract base | 2026-03-19 | [`56921141`](https://github.com/alexmerrill/Mukurtu-CMS/commit/56921141) |
| `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsTestBase.php` | Created + refactored to extend MukurtuKernelTestBase | 2026-03-19 | [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) [`56921141`](https://github.com/alexmerrill/Mukurtu-CMS/commit/56921141) |
| `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsSupportedProjectTest.php` | **Created** (13 tests) | 2026-03-19 | [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTestBase.php` | Created + refactored to extend MukurtuKernelTestBase | 2026-03-19 | [`840fb318`](https://github.com/alexmerrill/Mukurtu-CMS/commit/840fb318) [`56921141`](https://github.com/alexmerrill/Mukurtu-CMS/commit/56921141) |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaEntityTest.php` | **Created** (11 tests) | 2026-03-19 | [`840fb318`](https://github.com/alexmerrill/Mukurtu-CMS/commit/840fb318) |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTaxonomyTest.php` | **Created** (8 tests) | 2026-03-19 | [`840fb318`](https://github.com/alexmerrill/Mukurtu-CMS/commit/840fb318) |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php` | Created + refactored + CI fixes | 2026-03-19 / 2026-03-24 | [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) [`56921141`](https://github.com/alexmerrill/Mukurtu-CMS/commit/56921141) [`b5b7e29d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/b5b7e29d) [`f54f8953`](https://github.com/alexmerrill/Mukurtu-CMS/commit/f54f8953) [`03110274`](https://github.com/alexmerrill/Mukurtu-CMS/commit/03110274) |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryEntityTest.php` | **Created** (11 tests) | 2026-03-19 | [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryWordListTest.php` | **Created** (8 tests) | 2026-03-19 | [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTestBase.php` | **Created** | 2026-03-19 | [`2302d328`](https://github.com/alexmerrill/Mukurtu-CMS/commit/2302d328) |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageEntityTest.php` | **Created** (5 tests) + fix | 2026-03-19 | [`2302d328`](https://github.com/alexmerrill/Mukurtu-CMS/commit/2302d328) [`3a6367f6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/3a6367f6) |
| `modules/mukurtu_digital_heritage/tests/src/Kernel/DigitalHeritageTaxonomyTest.php` | **Created** (6 tests) | 2026-03-19 | [`2302d328`](https://github.com/alexmerrill/Mukurtu-CMS/commit/2302d328) |
| `modules/mukurtu_collection/tests/src/Kernel/CollectionTestBase.php` | **Created** | 2026-03-19 | [`d2061340`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d2061340) |
| `modules/mukurtu_collection/tests/src/Kernel/CollectionHierarchyTest.php` | **Created** (20 tests) + fix | 2026-03-19 / 2026-03-24 | [`d2061340`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d2061340) [`b5b7e29d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/b5b7e29d) |
| `modules/mukurtu_drafts/tests/src/Kernel/MukurtuDraftsBehaviorTest.php` | **Created** (10 tests) | 2026-03-24 | [`ac0b73eb`](https://github.com/alexmerrill/Mukurtu-CMS/commit/ac0b73eb) |
| `modules/mukurtu_multipage_items/tests/src/Kernel/MultipageItemTestBase.php` | **Created** + path_alias fix | 2026-03-24 | [`9618bddb`](https://github.com/alexmerrill/Mukurtu-CMS/commit/9618bddb) [`8c65781b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/8c65781b) |
| `modules/mukurtu_multipage_items/tests/src/Kernel/MultipageItemEntityTest.php` | **Created** (18 tests) | 2026-03-24 | [`9618bddb`](https://github.com/alexmerrill/Mukurtu-CMS/commit/9618bddb) |
| `modules/mukurtu_person/tests/src/Kernel/PersonTestBase.php` | **Created** | 2026-03-24 | [`d085dfa6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d085dfa6) |
| `modules/mukurtu_person/tests/src/Kernel/PersonEntityTest.php` | **Created** (17 tests) + fix | 2026-03-24 | [`d085dfa6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d085dfa6) [`caee4c0b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/caee4c0b) |
| `modules/mukurtu_place/tests/src/Kernel/PlaceTestBase.php` | **Created** | 2026-03-24 | [`d085dfa6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d085dfa6) |
| `modules/mukurtu_place/tests/src/Kernel/PlaceEntityTest.php` | **Created** (14 tests) + fix | 2026-03-24 | [`d085dfa6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d085dfa6) [`caee4c0b`](https://github.com/alexmerrill/Mukurtu-CMS/commit/caee4c0b) |
| `modules/mukurtu_community_records/tests/src/Kernel/CommunityRecordTestBase.php` | **Created** + path_alias fix | 2026-03-24 | [`e5ea5e23`](https://github.com/alexmerrill/Mukurtu-CMS/commit/e5ea5e23) [`daf384f4`](https://github.com/alexmerrill/Mukurtu-CMS/commit/daf384f4) |
| `modules/mukurtu_community_records/tests/src/Kernel/CommunityRecordFunctionsTest.php` | **Created** (12 tests) | 2026-03-24 | [`e5ea5e23`](https://github.com/alexmerrill/Mukurtu-CMS/commit/e5ea5e23) |
| `modules/mukurtu_browse/tests/src/Unit/MukurtuMapNodesParamConverterTest.php` | **Created** (7 tests) | 2026-03-24 | [`db1d016f`](https://github.com/alexmerrill/Mukurtu-CMS/commit/db1d016f) |
| `modules/mukurtu_browse/tests/src/Unit/MukurtuBoundingBoxTest.php` | **Created** (10 tests) | 2026-03-24 | [`db1d016f`](https://github.com/alexmerrill/Mukurtu-CMS/commit/db1d016f) |
| `phpunit.xml` | Added kernel + unit testsuite directories; PHPUnit 10 schema + @group fixes | 2026-03-19 / 2026-03-24 | [`12b71fca`](https://github.com/alexmerrill/Mukurtu-CMS/commit/12b71fca) [`56921141`](https://github.com/alexmerrill/Mukurtu-CMS/commit/56921141) [`bc26108d`](https://github.com/alexmerrill/Mukurtu-CMS/commit/bc26108d) [`840fb318`](https://github.com/alexmerrill/Mukurtu-CMS/commit/840fb318) [`2302d328`](https://github.com/alexmerrill/Mukurtu-CMS/commit/2302d328) [`d2061340`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d2061340) [`9618bddb`](https://github.com/alexmerrill/Mukurtu-CMS/commit/9618bddb) [`d085dfa6`](https://github.com/alexmerrill/Mukurtu-CMS/commit/d085dfa6) [`e5ea5e23`](https://github.com/alexmerrill/Mukurtu-CMS/commit/e5ea5e23) [`db1d016f`](https://github.com/alexmerrill/Mukurtu-CMS/commit/db1d016f) |

**Total new tests added: ~184** across 20 test classes and 10 modules.

---

## Design Notes & Open Questions

1. **`MukurtuProtocolNodeAccessControlHandler.php`** — the only production code change. The `any`/`all` branches are independent; verify that the `any` mode path is unaffected by the restructuring.

2. **`MukurtuMediaTestBase` — shared source field workaround** — `field_media_test_source` is a testing-only workaround. If Mukurtu ever moves media bundle fields from `hook_entity_field_storage_info` to config YAML, this becomes unnecessary.

3. **`DictionaryTestBase` — heavy module list** — loads ~30 modules. If the suite becomes slow, some lower-level dependencies (blazy, entity_browser, facets) could potentially be shimmed — at present none of their config is installed, so the overhead is just class loading.

4. **`LocalContextsTestBase::insertProjectRecord()`** — inserts directly to the DB to satisfy JOIN queries. Couples test setup to the schema, but gives more realistic coverage than testing only the non-JOIN paths.

5. **`DictionaryWordListTest::testAddSameWordTwiceResultsInDuplicate`** — `add()` does not deduplicate; the test asserts count=2 and documents that deduplication is the UI's responsibility. The docblock explicitly states that if deduplication is ever moved into the API, the test should assert count=1 instead. Confirm the intended boundary with the team.

6. **`mukurtu_browse` unit tests** — complement Playwright end-to-end coverage. The unit tests lock down bounding-box parsing math and CSV-to-entity delegation, which are the pieces Playwright can't easily reach.
