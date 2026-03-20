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
