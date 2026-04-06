# Mukurtu CMS — What the Tests Check (Plain Language)

This document explains what each automated test verifies, written for anyone who wants to understand the coverage without reading code. For technical detail — module names, file paths, commit references, and schema notes — see [coverage.md](coverage.md).

Tests are grouped by the feature they protect. Each item is one test that runs automatically on every code change.

---

## How the tests are structured

There are two kinds of tests in this codebase: **kernel tests** and **unit tests**.

**Kernel tests** spin up a real (in-memory) version of Drupal — real database, real entities, real services. They test that features actually work end-to-end within Drupal's infrastructure. The vast majority of Mukurtu's tests are kernel tests.

**Unit tests** test a single PHP class in complete isolation, with no Drupal or database involved. They're faster but narrower. Mukurtu has two: one for the map bounding box filter and one for the map URL parameter converter.

**On mocking:** Unit tests sometimes use "mock" objects — fake stand-ins for collaborators — to isolate the class being tested. Kernel tests do not use mocks because the whole point is to test against real Drupal behavior. The map parameter converter test uses a library called Prophecy for its mocks, which lets the test also verify *how* the class interacted with its dependencies, not just what it returned.

---

## Collections
*See [coverage.md § mukurtu_collection](coverage.md#mukurtu_collection)*

Collections are folders that can be nested inside each other. These tests confirm the hierarchy behaves correctly at every level.

**Parent/child relationships**
- A top-level collection has no parent.
- A child collection correctly identifies its parent.
- A grandchild's parent is the child — not the grandparent.
- Fetching a child's parent ID returns the right number.
- A leaf collection (no children) returns an empty child list.
- Replacing a collection's children wholesale replaces the old list.
- Removing a collection from its parent updates the parent and saves correctly.

**Root detection**
- A collection with no parent is correctly identified as a root.
- A collection that is someone's child is correctly identified as not a root.
- Fetching all root collections returns only top-level ones — not children.
- An isolated collection (no parent, no children) is also treated as a root.

**Traversal**
- Starting from the root and asking "what is my root?" returns itself.
- Starting from a child traverses up to the correct root.
- Starting from a grandchild traverses all the way up to the root.
- Fetching the full hierarchy returns a nested structure with correct depth numbers.
- Setting a maximum depth stops traversal at that level.
- A leaf collection returns an empty children list in the hierarchy.

**Identification**
- Looking up a collection from a collection node returns the collection.
- Looking up a collection from a non-collection node returns nothing.

---

## Digital Heritage Items
*See [coverage.md § mukurtu_digital_heritage](coverage.md#mukurtu_digital_heritage)*

**Structure**
- A digital heritage item is recognized as the correct content type and has all required capabilities.
- The category field is required; the description field is not.
- Fields that can hold multiple values (keywords, creators, related content, etc.) are confirmed to be multi-value.
- Single-value fields (description, date, identifier) are confirmed to hold only one entry.
- Keyword and creator terms are created automatically when you type a new one; category terms are not (they must be chosen from a manager-controlled list).

**Saving and validation**
- Protocol and sharing settings save and reload correctly.
- Trying to save a digital heritage item without a category triggers a validation error.

**Taxonomy (keywords, creators)**
- Typing a new keyword on save creates the keyword term automatically.
- Multiple keywords can be saved in order.
- Using the same keyword on two different items reuses the term — it is not duplicated.
- Multiple creators can be added to a single item.
- Two digital heritage items can reference each other as related content.

**Access**
- A user with no protocol membership cannot see content under a strict protocol.
- A protocol member can see that same content.

---

## Media (Audio, Image, Document)
*See [coverage.md § mukurtu_media](coverage.md#mukurtu_media)*

**Structure and field rules — per bundle**
- Audio, Image, and Document are each recognized as their own content type with the correct capabilities.
- Document additionally has thumbnail generation capability; audio and image do not.
- Required and optional fields are confirmed for each bundle type.
- Multi-value vs single-value fields are confirmed per bundle.
- All taxonomy reference fields (tags, contributors, people) automatically create new terms when you type one.

**Saving**
- Protocol and sharing settings save and reload correctly across all three media types.
- The identifier field persists correctly on audio, image, and document.

**Taxonomy**
- Adding a new media tag on save creates the term automatically.
- Multiple tags save in order.
- Reusing an existing tag by ID does not create a duplicate.
- Multiple contributors can be added to a single media item.
- People tags work on both audio and document.

**Access**
- A user with no protocol membership cannot see media under a strict protocol.
- A protocol member can see that same media.

**Image alt text**
- Uploading an image without alt text triggers a validation error.
- Providing alt text passes validation.

---

## Dictionary
*See [coverage.md § mukurtu_dictionary](coverage.md#mukurtu_dictionary)*

**Structure**
- Dictionary words, word lists, word entries, and sample sentences are each recognized as their own content type.
- The language field is the only required field on a dictionary word; everything else is optional.
- Multi-value and single-value fields are confirmed.
- The keywords and word-type fields auto-create new terms; the language field does not (languages are manager-controlled).

**Automatic behavior on save**
- When a dictionary word is saved without a glossary entry letter, the first letter of the title is filled in automatically.
- When a glossary entry letter has been set manually, it is not overwritten on save.
- Multi-byte first characters (e.g., accented or non-Latin letters) are handled correctly.

**Access control**
- Creating a dictionary word is allowed when at least one language term exists in the system.
- Creating a dictionary word is blocked when no language terms exist.

**Saving**
- Protocol and sharing settings save and reload correctly.
- The language field references the correct term after save.
- Using a second, different language term on a new word works correctly.

**Word lists**
- A new word list starts with zero words.
- Adding a word increases the count and the word is present after save.
- Multiple words save in insertion order.
- Removing a specific word from the list removes that word and decreases the count.
- Trying to remove a word that isn't in the list does nothing.
- Removing all words leaves the list empty.
- The word count reflects unsaved additions immediately (before save).
- Adding the same word twice results in two entries (the system does not deduplicate — the interface is expected to enforce uniqueness).
- Saving a word list with referenced words completes without error.

---

## Local Contexts Labels & Projects
*See [coverage.md § mukurtu_local_contexts](coverage.md#mukurtu_local_contexts)*

Local Contexts projects can be supported at the site level or by individual communities (groups).

- Adding a project at the site level makes it detectable as a site project.
- Adding the same project twice at the site level does not create a duplicate entry.
- An unknown project is correctly reported as not supported.
- Adding a project to a community makes it detectable for that community.
- Adding the same community project twice does not duplicate it.
- A site-level project is not reported as a community project for a specific group (scopes are kept separate).
- Fetching all site projects returns them correctly after the underlying database join.
- Group projects are excluded from the site projects list.
- Fetching projects for a specific community returns only that community's projects.
- Fetching projects for a different community returns nothing.
- Removing a site project deletes the project record entirely when nothing else references it.
- Removing a site project leaves the project record intact when a community still references it.
- Removing a community project deletes the record when there are no other references.
- Force-deleting a project removes it even if other references exist.
- Removing a project without force-delete is a no-op when the project is still in use.
- Fetching site projects with "exclude legacy" filters out legacy TK label IDs.

---

## Drafts
*See [coverage.md § mukurtu_drafts](coverage.md#mukurtu_drafts)*

Drafts are content that has been saved but is not yet published.

- A newly created item is not a draft by default.
- Marking an item as a draft works.
- Unmarking a draft returns it to non-draft status.
- Both mark and unmark operations support chaining (e.g., `item->setDraft()->save()`).
- Draft status is preserved after saving and reloading.
- Non-draft status is also preserved after saving and reloading.
- A draft item gets a visual "unpublished" indicator in its page build.
- A non-draft item does not get that indicator.
- An anonymous (logged-out) user is blocked from viewing a draft.
- An anonymous user gets neutral access on a non-draft (other rules decide).

---

## Multipage Items
*See [coverage.md § mukurtu_multipage_items](coverage.md#mukurtu_multipage_items)*

Multipage items link a series of content pages into an ordered sequence.

**Basic behavior**
- A newly created multipage item is recognized as the correct type.
- A new multipage item with no pages returns an empty page list.
- Adding a single page makes it retrievable.
- Adding multiple pages preserves their insertion order.
- Checking whether a page is in the multipage item returns true when it is and false when it is not.
- Asking for the first page when no pages exist returns nothing.
- Asking for the first page when pages exist returns the first one added.

**Reordering pages**
- Setting the first page on an empty multipage item makes it the only page.
- Setting the first page to a new node prepends it, leaving existing pages after.
- Moving a mid-list page to the front reorders correctly without duplicating it.
- Setting the first page when it is already first leaves the list unchanged with no duplicates.

**Access and filtering**
- Fetching pages without an access check returns all pages including unpublished ones.
- Fetching pages with an access check filters out unpublished pages.

**Finding the multipage item from a page**
- Given a page node, the system correctly finds the multipage item it belongs to.
- A page that belongs to no multipage item returns nothing.
- When multiple multipage items exist, the correct one is returned.

**Configuration**
- A bundle type marked as enabled in settings is correctly reported as enabled.
- A bundle type not enabled in settings is correctly reported as disabled.
- A bundle type not mentioned at all in settings is also reported as disabled.

---

## People
*See [coverage.md § mukurtu_person](coverage.md#mukurtu_person)*

People are content records about individuals (historical or contemporary).

**Structure**
- A person record is recognized as the correct content type with the right capabilities (protocol control, draft support).
- Keywords, media assets, sections, related people, related content, other names, and location fields all hold multiple values.
- Date of birth and date of death are single-value fields.
- The deceased flag, coverage, and coverage description are single-value.
- Birth place and death place reference the location vocabulary, each holding one value.
- Location field holds multiple values and references the location vocabulary.
- All person fields are optional — none are required to save a record.

**Saving**
- Protocol and sharing settings persist through save and reload.
- Draft status persists through save and reload.
- A new person is not a draft by default.
- The deceased flag defaults to false.

---

## Places
*See [coverage.md § mukurtu_place](coverage.md#mukurtu_place)*

Places are content records about locations (physical or cultural).

**Structure**
- A place record is recognized as the correct content type with the right capabilities.
- Keywords, place type, media assets, sections, related content, other place names, and location fields all hold multiple values.
- Place type references the place-type vocabulary (not the people vocabulary used by Person).
- Coverage and coverage description are single-value.
- Location field holds multiple values and references the location vocabulary.
- A place record does not have date-of-birth or date-of-death fields (those belong only to Person).
- All place fields are optional.

**Saving**
- Protocol and sharing settings persist through save and reload.
- Draft status persists through save and reload.
- A new place is not a draft by default.

---

## Community Records
*See [coverage.md § mukurtu_community_records](coverage.md#mukurtu_community_records)*

Community records allow communities to add their own perspective on an existing content item (the "original record").

**Field presence**
- A content type configured to support community records correctly reports it has the original record field.
- A content type not configured for community records correctly reports it does not have that field.
- The page content type (field installed) is reported as supporting community records.
- The basic page content type (field not installed) is reported as not supporting community records.

**Detecting record type**
- A node on a bundle without the field always returns false for "is this a community record?".
- A node on a CR-enabled bundle with the field empty returns false.
- A node with the original record field pointing at another node returns that other node's ID.
- A node that no community records point to is not an original record.
- A node that one community record points to is identified as an original record, returning that CR's ID.
- A node that two community records point to returns both IDs.
- Non-node entities (e.g., media, taxonomy terms) always return false.

**Validation rules**
- A node cannot set itself as its own original record (circular reference is blocked).
- A node that is already a community record cannot be used as an original record (prevents nesting).
- A node that already has community records pointing to it cannot itself become a community record.
- A valid community record pointing at a clean original record passes validation with no errors.

---

## Imports — List String Fields
*See [coverage.md § mukurtu_import](coverage.md#mukurtu_import)*

When importing content from a CSV file, fields that accept a fixed list of values (like Creative Commons licenses) can be matched by either the raw stored value or the human-readable label.

- Providing an exact stored key in the CSV (e.g. a full license URL) imports that value correctly.
- Providing the human-readable label (e.g. "Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0)") is looked up and converted to the correct stored key on import.
- Multiple values in a single cell separated by semicolons all import correctly, mixing keys and labels.

---

## Browse — Map and Bounding Box
*See [coverage.md § mukurtu_browse](coverage.md#mukurtu_browse)*

The browse map accepts a geographic area (bounding box) and a list of content IDs from the URL and uses them to filter results.

**Loading content from URLs**
- A single content ID in the URL loads exactly that one item.
- Multiple comma-separated IDs load all of them.
- Whatever the storage layer returns is passed through unchanged.
- The converter only activates when the URL parameter type is exactly "nodes".
- It does not activate for other type names.
- It does not activate when the type key is missing from the definition.
- It does not activate when the type key is present but empty.

**Parsing geographic coordinates**
- A valid "left, bottom, right, top" coordinate string is parsed into four named values.
- Integer coordinates are converted to decimal numbers automatically.
- Fewer than four coordinates produce no result (invalid input is rejected).
- More than four coordinates produce no result.
- An empty coordinate string produces no result.
- Non-numeric strings are cast to zero but the structure is still returned (value validity is not checked at parse time).

**Applying coordinates to a search query**
- A valid bounding box causes exactly four conditions to be added to the search query (one per edge).
- An invalid bounding box causes no conditions to be added.
- The correct field names (`centroid_lat`, `centroid_lon`) and comparison operators (≥ and ≤) are used.
