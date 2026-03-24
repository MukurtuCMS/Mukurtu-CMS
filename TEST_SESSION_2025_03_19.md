# Test Infrastructure Session — 2025-03-19

## Overview

Two bodies of work were completed in this session:

1. **Shared test base class** — extracted ~50 lines of duplicated kernel test boilerplate into a single abstract parent class, then refactored three existing test bases to extend it.
2. **Collection hierarchy tests** — wrote a new test base and 20-test class covering the `CollectionHierarchyService` and Collection entity methods that had no existing coverage.

---

## Part 1: `MukurtuKernelTestBase` and test base refactoring

### Problem

Three kernel test base classes (`LocalContextsTestBase`, `MukurtuMediaTestBase`, `DictionaryTestBase`) each independently duplicated the same ~50-line boilerplate for:

- Schema and entity schema installation (system, file, mukurtu_protocol, mukurtu_local_contexts, user, OG, etc.)
- `installConfig(['filter', 'og', 'system'])`
- OG group registration (`Og::addGroup`)
- Authenticated role creation
- Protocol steward OG role creation
- Current user, community, and protocol fixture creation

This made each new test base a copy-paste exercise and caused cascading fix cycles when a missing schema was discovered — fixing one surfaced the next.

### Solution

**New file:** `modules/mukurtu_core/tests/src/Kernel/MukurtuKernelTestBase.php`

Abstract base class that owns all shared infrastructure:

- Blazy `file.repository` workaround — synthetic DI stub in `register()` + `createMock(FileRepository::class)` in `setUp()` to satisfy `BlazyFile::__construct()` type check in Drupal 10 (service was removed from D9→D10)
- Schema installs: `system/sequences`, `file/file_usage`, `mukurtu_protocol` map+access tables, all six `mukurtu_local_contexts` tables
- Entity schemas: `user`, `file`, `taxonomy_term`, `taxonomy_vocabulary`, `og_membership`, `workflow`, `community`, `protocol`
- Config: `filter`, `og`, `system`
- OG group registration, authenticated role, protocol steward OG role
- `$this->currentUser`, `$this->community`, `$this->protocol` fixtures
- `getProtocolStewardPermissions(): array` hook — subclasses override this to add content-type-specific CRUD OG permissions without reconstructing the entire role

**Important PHP note:** A child class that re-declares `$modules` completely replaces the parent's list (PHP static property override, no automatic merging). Any subclass needing additional modules must re-declare the full list.

### Refactored files

**`modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsTestBase.php`**
- 169 → 55 lines
- Module list was identical to parent's — `$modules` declaration dropped entirely
- `setUp()` reduced to one line: `$this->manager = $this->container->get(...)`
- `insertProjectRecord()` helper retained

**`modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTestBase.php`**
- 219 → 132 lines
- Re-declares `$modules` with media-specific additions (`flag`, `tagify`, `block_content`, `media_entity_soundcloud`, `content_moderation`, `mukurtu_media`)
- `setUp()` retains only the media-specific setup sequence: FieldStorageConfig → `installEntitySchema('media')` → MediaType bundle creation → explicit FieldConfig per bundle → `clearCachedFieldDefinitions()` → vocabularies
- This ordering is required because `mukurtu_media`'s `hook_entity_field_storage_info()` registers bundle fields as shared storage definitions, which can trigger field manager cache rebuilds that lose newly-created FieldConfigs

**`modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php`**
- 291 → 185 lines
- Re-declares `$modules` with its large addition set (blazy, paragraphs, search_api, comment, path_alias, etc.)
- Overrides `getProtocolStewardPermissions()` to append dictionary CRUD OG permissions (`create/delete/update dictionary_word node`)
- `setUp()` adds: comment/node schemas, `Role::load('authenticated')->grantPermission(...)` for dictionary node creation, entity schemas for comment/path_alias/search_api/node/paragraph/language_community, NodeType and ParagraphsType creation, vocabularies, language term

**`phpunit.xml`**
- Added `web/profiles/mukurtu/modules/mukurtu_core/tests/src/Kernel` to the kernel testsuite to scan for future concrete tests in the core module

---

## Part 2: Collection hierarchy tests

### Context

`mukurtu_collection` already had two test files:
- `CollectionEntityTest` (extends `ProtocolAwareEntityTestBase`) — covers validation constraints (self-reference, duplicates, circular hierarchy), `add()`/`remove()`, `getCount()`, `getChildCollections()`
- `PersonalCollectionEntityAccessTest` — covers `PersonalCollection` access control and field getters/setters

### Gap

The following were untested:
- `getParentCollection()` / `getParentCollectionId()` — entity query traversal up the hierarchy
- `getChildCollectionIds()` — raw ID array
- `setChildCollections()` — bulk replace
- `removeAsChildCollection()` — self-removal from parent's field + parent save
- All four public methods of `CollectionHierarchyService` (`isRootCollection`, `getRootCollections`, `getRootCollectionForCollection`, `getCollectionHierarchy`)
- Bundle class identity assertion (that `Node::load()` returns a `Collection` instance)

### New files

**`modules/mukurtu_collection/tests/src/Kernel/CollectionTestBase.php`**

Extends `MukurtuKernelTestBase`. Adds:
- Module list: `block_content`, `content_moderation`, `geofield`, `node_access_test`, `mukurtu_collection`, `mukurtu_drafts` (plus the full parent list)
- `installEntitySchema('node')` + `installSchema('node', ['node_access'])`
- NodeType `collection` and `page`
- Vocabularies `keywords` and `location` (required by `Collection::bundleFieldDefinitions()`)
- OG permission override: adds collection CRUD permissions to the protocol steward role
- `buildCollection(string $title): Collection` helper — unsaved, protocol+sharing set
- `buildItem(string $title): Node` helper — unsaved `page` node

**`modules/mukurtu_collection/tests/src/Kernel/CollectionHierarchyTest.php`**

20 tests using a shared setUp fixture of a three-level hierarchy (`root → child → grandchild`, all saved):

| Group | Tests |
|---|---|
| Bundle class identity | `testCollectionBundleClass` |
| `getParentCollection()` | `_noParent`, `_withParent`, `_grandchild` |
| `getParentCollectionId()` | `_noParent`, `_withParent` |
| `getChildCollectionIds()` | `_hasChildren`, `_leaf` |
| `setChildCollections()` | replaces existing children wholesale |
| `removeAsChildCollection()` | DB-reloads parent to confirm save |
| `isRootCollection()` | `_true`, `_false` |
| `getRootCollections()` | filters children out; isolated collection qualifies |
| `getRootCollectionForCollection()` | `_alreadyRoot`, `_fromChild`, `_fromGrandchild` |
| `getCollectionHierarchy()` | correct depth/children structure; `max_depth` truncation; leaf case |
| `getCollectionFromNode()` | `_collection`, `_nonCollection` |

Service is retrieved from the container as `mukurtu_collection.hierarchy_service`.

`phpunit.xml` updated to include `mukurtu_collection/tests/src/Kernel`.

---

## Files changed

| File | Change |
|---|---|
| `modules/mukurtu_core/tests/src/Kernel/MukurtuKernelTestBase.php` | **Created** |
| `modules/mukurtu_local_contexts/tests/src/Kernel/LocalContextsTestBase.php` | Refactored — extends MukurtuKernelTestBase |
| `modules/mukurtu_media/tests/src/Kernel/MukurtuMediaTestBase.php` | Refactored — extends MukurtuKernelTestBase |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php` | Refactored — extends MukurtuKernelTestBase |
| `modules/mukurtu_collection/tests/src/Kernel/CollectionTestBase.php` | **Created** |
| `modules/mukurtu_collection/tests/src/Kernel/CollectionHierarchyTest.php` | **Created** |
| `phpunit.xml` | Added mukurtu_core and mukurtu_collection Kernel test directories |

## Candidates identified for next sessions

Ranked by testability of untested business logic:
1. `mukurtu_multipage_items` — custom content entity with ~15 page-management methods
2. `mukurtu_digital_heritage` — existing base uses old `KernelTestBase` directly; migrate + expand coverage
3. `mukurtu_drafts` — existing test covers basic owner access; draft access hook has uncovered branches

---

# Test Infrastructure Session — 2026-03-24

## Overview

Two CI failures were fixed, then kernel test coverage was added for five previously untested modules (`mukurtu_drafts`, `mukurtu_multipage_items`, `mukurtu_person`, `mukurtu_place`, `mukurtu_community_records`), and PHPUnit unit tests were added for two classes in `mukurtu_browse`.

---

## Part 1: CI Fixes

### Fix A — `CollectionHierarchyTest::testGetRootCollections` (assertContains type mismatch)

**Root cause:** PHPUnit 10 uses strict type comparison in `assertContains`. `array_keys()` on an entity `loadMultiple()` result returns `int[]`, but `$entity->id()` returns `string`. `assertContains('1', [1])` fails.

**Fix:** Replaced `assertContains`/`assertNotContains` with `assertTrue(in_array(...))` / `assertFalse(in_array(...))`, which uses loose comparison and handles the string/int mismatch correctly.

### Fix B — `DictionaryEntityTest::testBundleCheckCreateAccessForbiddenWithoutLanguage` (TransactionOutOfOrderException)

Root cause pattern: a missing database table causes MariaDB to implicitly roll back the transaction. When Drupal then tries to roll back explicitly, the transaction stack is empty → `TransactionOutOfOrderException`. Required two rounds:

**Round 1:** `search_api` module's entity delete hook queries `search_api_item` for every deleted entity. Added `installSchema('search_api', ['search_api_item'])`. Attempted `search_api_task` simultaneously — this table does not exist in the installed version and threw a `LogicException` for all 24 dictionary tests. Removed `search_api_task`.

**Round 2:** `layout_builder_entity_delete()` unconditionally calls `removeByLayoutEntity()` for every entity type, including taxonomy terms, which queries `inline_block_usage`. Added `installSchema('layout_builder', ['inline_block_usage'])`.

**General rule:** Any kernel test that deletes entities (including taxonomy terms via `node_access_rebuild()`) while `layout_builder` and `search_api` are in the module list must install both of these tables.

---

## Part 2: New test coverage

### `mukurtu_drafts` — `MukurtuDraftsBehaviorTest` (10 tests)

Second test class alongside the existing `MukurtuDraftsEntityTest`, same namespace and module list. Uses the existing `TestDraftEntity` fixture (from `drafts_entity_test` module). Covers:
- `isDraft()` default false, `setDraft()`, `unsetDraft()`, fluent chaining for both
- Draft status persists through save/reload; non-draft status also persists
- `mukurtu_drafts_entity_view()` hook injects `node--unpublished` CSS class for drafts and does not inject it for non-drafts
- `hook_entity_access`: anonymous user is forbidden from viewing a draft entity; neutral on non-draft (defers to other handlers)

### `mukurtu_multipage_items` — `MultipageItemTestBase` + `MultipageItemEntityTest` (18 tests)

`MultipageItemTestBase` extends `CollectionTestBase` (which provides the OG/node/protocol setup). Additions: `path_alias` module + `installEntitySchema('path_alias')` (required because `mukurtu_multipage_items`' node insert/update hook calls `path_alias.manager`), `multipage_item` entity schema, `mukurtu_multipage_items.settings` config. Provides `buildMultipageItem()`.

Tests cover: entity class + `MultipageItemInterface`; `addPage()`/`getPages()`/`hasPage()`/`getFirstPage()`; `setFirstPage()` (prepend to empty, prepend new node, move existing to front, already-first is no-op); `getPages($accessCheck)` with published/unpublished filtering; `MultipageItemManager::getMultipageEntity()` (found, not found, correct MPI among multiples); `MultipageItemManager::isEnabledBundleType()` (enabled, disabled, not-in-config).

### `mukurtu_person` — `PersonTestBase` + `PersonEntityTest` (17 tests)

Extends `MukurtuKernelTestBase`. Module list is the base list plus `original_date`, `geofield`, `paragraphs`, `entity_reference_revisions`, `layout_builder`, `node`, `node_access_test`, `mukurtu_person`, and others. **Deliberately omits** `mukurtu_browse` and `mukurtu_taxonomy` — their dependency chains (~30 modules) are not needed because Person's module hooks do not call browse/taxonomy services at runtime.

Tests cover: bundle class + `PersonInterface` + `CulturalProtocolControlledInterface` + `MukurtuDraftInterface`; cardinality for all 13 bundle fields; all bundle fields are optional; location/birth/death fields target the `location` vocabulary; protocol field persistence via `getProtocolEntities()` (not `getProtocols()`, which returns IDs); draft persistence; `field_deceased` defaults FALSE.

**Bug found and fixed during testing:** `getProtocols()` returns a raw ID array (integers), not entity objects. Tests that assert on protocol identity must call `getProtocolEntities()` instead.

### `mukurtu_place` — `PlaceTestBase` + `PlaceEntityTest` (14 tests)

Same pattern as Person. Uses `place_type` vocabulary instead of `people`. Key difference asserted explicitly: Place has no `field_date_born` or `field_date_died` (unlike Person).

### `mukurtu_community_records` — `CommunityRecordTestBase` + `CommunityRecordFunctionsTest` (12 tests)

Community records are not a custom entity type — they are regular nodes with a `field_mukurtu_original_record` entity reference field. The field storage is created programmatically via `FieldStorageConfig::create()` (not `installConfig()`) for explicit control. A `page` bundle has the field; a `basic_page` bundle does not.

Tests cover: `mukurtu_community_records_has_record_field()` with/without field; `mukurtu_community_records_entity_type_supports_records()` enabled/disabled bundle; `mukurtu_community_records_is_community_record()` (no field, empty field, field set); `mukurtu_community_records_is_original_record()` (no CRs, single CR, multiple CRs, non-node entity short-circuits to FALSE); `ValidOriginalRecord` constraint (circular self-reference, target is a CR, entity already has CRs pointing to it, valid case with 0 violations).

**Constraint test design:** All constraint tests operate on already-saved entities. For new entities the validator also runs a `Url::access()` check against the CR creation route, which is not available in kernel tests. By saving the entity first (`$entity->isNew() === false`), that branch is skipped and only the field-logic violations are exercised.

**Fix needed after initial CI run:** `$entity->validate()` traverses the node's `path` computed field, which calls `path_alias.repository`. Added `path_alias` to `$modules` and `installEntitySchema('path_alias')` to the test base.

---

## Part 3: PHPUnit unit tests for `mukurtu_browse`

`mukurtu_browse`'s dependency tree (~30 modules including `search_api`, `leaflet`, `facets`, `mukurtu_dictionary`, `mukurtu_digital_heritage`) makes kernel tests impractical. The two classes with isolated business logic were targeted with plain PHPUnit unit tests (no Drupal bootstrap).

A `unit` testsuite was added to `phpunit.xml`.

### `MukurtuMapNodesParamConverterTest` (7 tests)

`EntityTypeManagerInterface` is constructor-injected → straightforward mock. Tests: `convert()` with single ID, multiple CSV IDs, result passthrough; `applies()` true for `type: nodes`, false for other type, false when key missing, false for empty type.

### `MukurtuBoundingBoxTest` (10 tests)

`MukurtuBoundingBox` extends `SearchApiStandard` (deep Drupal Views DI chain). Instance created via `getMockBuilder()->disableOriginalConstructor()->onlyMethods([])->getMock()` to skip the constructor while keeping real method implementations. `parseBoundingBox()` (protected) called via `ReflectionMethod`. `$this->query` set via `ReflectionProperty` (no `setAccessible(true)` needed in PHP 8.1+). `addCondition()` calls intercepted via a `stdClass` mock with `addMethods(['addCondition'])`.

Tests: valid 4-value CSV parsed to correct float keys; integers cast to float; too few coordinates returns empty; too many returns empty; empty string returns empty; non-numeric strings cast to 0.0 (method only validates count). `query()`: valid bbox triggers exactly 4 `addCondition()` calls; invalid bbox triggers none; correct field names (`centroid_lat`/`centroid_lon`) and operators (`>=`/`<=`) verified via callback.

---

## Files changed

| File | Change |
|---|---|
| `modules/mukurtu_collection/tests/src/Kernel/CollectionHierarchyTest.php` | Fix: `assertContains` → `in_array()` |
| `modules/mukurtu_dictionary/tests/src/Kernel/DictionaryTestBase.php` | Fix: add `search_api_item` + `inline_block_usage` schemas |
| `modules/mukurtu_drafts/tests/src/Kernel/MukurtuDraftsBehaviorTest.php` | **Created** (10 tests) |
| `modules/mukurtu_multipage_items/tests/src/Kernel/MultipageItemTestBase.php` | **Created** |
| `modules/mukurtu_multipage_items/tests/src/Kernel/MultipageItemEntityTest.php` | **Created** (18 tests) |
| `modules/mukurtu_person/tests/src/Kernel/PersonTestBase.php` | **Created** |
| `modules/mukurtu_person/tests/src/Kernel/PersonEntityTest.php` | **Created** (17 tests) |
| `modules/mukurtu_place/tests/src/Kernel/PlaceTestBase.php` | **Created** |
| `modules/mukurtu_place/tests/src/Kernel/PlaceEntityTest.php` | **Created** (14 tests) |
| `modules/mukurtu_community_records/tests/src/Kernel/CommunityRecordTestBase.php` | **Created** |
| `modules/mukurtu_community_records/tests/src/Kernel/CommunityRecordFunctionsTest.php` | **Created** (12 tests) |
| `modules/mukurtu_browse/tests/src/Unit/MukurtuMapNodesParamConverterTest.php` | **Created** (7 tests) |
| `modules/mukurtu_browse/tests/src/Unit/MukurtuBoundingBoxTest.php` | **Created** (10 tests) |
| `phpunit.xml` | Added 6 kernel directories + 1 unit testsuite |
