# Taxonomy hierarchy audit

This audit covers what it would take to support parent and child terms in
Mukurtu CMS ([#1517](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1517)).
It lists every part of the site that a nested vocabulary touches, what breaks
or is missing today, and a proposed fix for each.

This is an audit only. It doesn't change any code.

## Summary

Mukurtu blocks term hierarchies on purpose today. Hierarchy can be switched
on one vocabulary at a time without much effort, but most of the site would
then ignore it, and some features would silently lose or flatten the
hierarchy:

- **Admin.** Allowing nesting is a small change. The contrib `flat_taxonomy`
  module already works per vocabulary. Mukurtu's own guards are the only
  thing that locks it on.
- **Search and facets.** These can't follow hierarchy as configured. Every
  search index stores term names, not term IDs, and no index records a
  term's ancestors.
- **Import, export, and v3 migration.** Each of these can silently turn a
  child term into a top-level term.
- **Display.** Term pages, content pages, and form widgets show no parent
  context. Two terms with the same name under different parents look
  identical everywhere.
- **Data loss.** Deleting or merging a parent term deletes its child terms.

The recommended scope is **opt-in per vocabulary**. The stock vocabularies
stay flat by default, and nothing changes for existing sites until an
administrator turns hierarchy on.

### How the findings were checked

Every finding comes from reading the Mukurtu source on `main` (as of
`f6b590ecf`) and the installed contrib and core source. No finding was
reproduced on a running site yet. Findings marked **(spot-checked)** were
read a second time directly in the source to confirm the line references.

## Current blockers

These pieces keep every vocabulary flat today:

| Blocker | Location | Effect |
|---|---|---|
| Stock vocabulary config | `third_party_settings.flat_taxonomy.flat: 1` in all 16 `taxonomy.vocabulary.*.yml` files (`mukurtu_core`, `mukurtu_protocol`, `mukurtu_dictionary`) | Marks each vocabulary as flat. |
| Contrib `flat_taxonomy` (`^2.0`, `composer.json:46`) | `flat_taxonomy.module` | Hides the parent field, rejects a parent on validation, removes the nesting columns from the term overview, removes the **Add child** operation, and resets any parent to 0 on presave (including imports and migrations). |
| Locked **Flat taxonomy** checkbox | `mukurtu_taxonomy.module:118-143` | Forces the checkbox on and disables it for **every** vocabulary, including ones that administrators create (#1788). **(spot-checked)** |
| Hidden **Relations** fieldset | `mukurtu_taxonomy.module:74-107`, and again for Category at `:166-169` | Hides parent and weight on all 16 stock vocabularies. This check ignores the flat setting, so turning off flat alone would still leave no parent field. **(spot-checked)** |
| Import help text | `mukurtu_import/src/Form/ImportFieldDescriptionListForm.php:48` | Tells users that `parent` is "Not currently supported in Mukurtu." **(spot-checked)** |
| Tests | `mukurtu_taxonomy/tests/src/Unit/FlatTaxonomyGuardTest.php`, `tests/src/Kernel/FlatTaxonomyCheckboxRenderTest.php` | Assert the current flat-only behavior. |

Background: [#204](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/204)
hid the hierarchy UI until the project decided whether to support
hierarchy. #1517 is that decision.

## Findings by area

Size estimates: **S** is a few hours, **M** is one or two days, **L** is
several days or needs design decisions first.

### 1. Opt-in mechanism and term admin

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| `flat_taxonomy` already stores flat as a per-vocabulary setting. Unchecking it unsets the setting, and all of its guards key off that setting. | Mukurtu's checkbox lock prevents unchecking it. | Remove the lock. Keep `flat_taxonomy` as the per-vocabulary switch. Replace the lock's description with a warning about what flattening does (see [Proposed user-facing text](#proposed-user-facing-text)). | S |
| Relations is hidden on stock vocabularies regardless of the flat setting. | No parent field on a stock vocabulary even after opting in. | Hide Relations only when the vocabulary is flat. Merge the duplicate Category alter. | S |
| Turning flat back on runs `Flattener::flatten()` immediately, which re-saves every term at the top level. The contrib module has an unfinished `@todo` for a confirmation step. | Opting out silently destroys the hierarchy. | Add a confirmation step, or block re-enabling flat while nested terms exist. | M |
| Because the checkbox is locked on, `flatten()` already runs on **every** vocabulary form save today and re-saves every term in the vocabulary. | Existing performance issue, unrelated to hierarchy. | Fixed as a side effect of removing the lock, or only call `flatten()` when the value changes. | S |
| The custom manage pages (`MukurtuManageTaxonomyController`, `MukurtuCategoryManageController`) embed core's overview form. | Drag-to-nest will appear once flat is off. Needs an accessibility check: core tabledrag nesting relies on drag or the "Show row weights" fallback. | Test keyboard and screen reader use of nesting. The parent field on the term form is the accessible fallback, so keep it visible. | S |

### 2. Term record page

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| The term page (`TaxonomyRecordViewController.php:162-177`) embeds `views.view.taxonomy_term`, whose argument is `taxonomy_index_tid` (`views.view.taxonomy_term.yml:98`). **(spot-checked)** | A parent's page doesn't list content tagged with its children. | Switch to `taxonomy_index_tid_depth` with a depth setting. Add the vocabulary's `taxonomy_term_list` cache tag so the page updates when terms move. | M |
| `taxonomy-records.html.twig` shows the description, records, and referenced content. | No list of child terms. | Add a translated, access-checked list of child terms. | M |
| Core's `TermBreadcrumbBuilder` already applies to the term route, and the theme appends the page title (`mukurtu_v4.theme:26-40`). | The breadcrumb already shows ancestors. Core doesn't check access on ancestor terms, so an unpublished parent's name could appear. | Decide whether unpublished parents matter, and filter them if so. | S |
| The page title is "{Vocabulary}: {term name}" (`TaxonomyRecordViewController.php:97-99`). | Same-named terms under different parents have identical titles. | The breadcrumb gives context. Optionally add the parent to the title. | S |
| Media is never added to `taxonomy_index`. | Media tagged with a term never shows on the term page. This is true today and doesn't depend on hierarchy. | Out of scope, but worth a separate issue. | n/a |

### 3. Content pages

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| Term fields use core `entity_reference_label` on 61 view displays. | Only the term name shows. Same-named terms look identical. | Decide whether content pages show the leaf name only or the full path. A small custom formatter could offer both. | M |
| `CombinedTermLabelFormatter.php:39-76` (Person and Place full displays) removes terms whose translated label matches one already shown. | With hierarchy, it merges different terms that share a name into one link. | Compare by term ID, not label. | S |
| The category teaser links to browse filtered by category **name** (`BrowseLinkBuilder.php:56-57`, `mukurtu_v4.theme:583-586`). | The results leave out child categories, and same-named categories are mixed together. | Link by term ID once the search index has ID fields (area 5). | M |

### 4. Form widgets

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| Most term fields use the Tagify autocomplete widget (`parent_selection: 1`). Tagify groups suggestions under a parent heading, using `TagifyHierarchicalTermManager::getParentName()`. | The parent name comes from untranslated `loadTree()` rows, and the JavaScript matches headings by label, so grouping breaks on translated sites. Grouping relies on visual headings; screen reader behavior is untested. | Fix translation in Tagify (contrib patch or upstream). Test the suggestion list with a screen reader. Consider turning on `show_info_label` with a parent token to tell same-named terms apart. | M |
| Tagify looks up typed text by name and uses the first match (`massageFormValues`, `TagifyEntityReferenceAutocompleteWidget.php:316-388`). New terms are created at the top level. | Typed names silently pick the wrong term when names repeat. Editors can't create a child term inline. | Accept that inline creation is always top level, and document it. Prefer selecting from suggestions, which carries the term ID. | S |
| Many content fields allow creating terms (`auto_create`), including keywords, location, people, and subject on Digital Heritage, Person, Place, Dictionary, Collection, and every media type. Category, dictionary language, and community type don't. | Same as above. | Same as above. | n/a |
| Category, community type, and dictionary language use `options_buttons`. Core prefixes nested labels with dashes ("-Child", "--Grandchild"). | Screen readers read the dashes, and there's no visual indentation or grouping. Fails the spirit of WCAG 1.3.1. | Theme override or small widget that renders nesting as indentation with grouping (for example, nested fieldsets), not dashes. | M |
| Collection, Article, and a few other fields use core autocomplete, which rejects ambiguous names with an error. | Editors see an error when names repeat. | Acceptable. The error is clear. | n/a |

Hierarchy-specific widget modules such as `cshs`, `shs`, and
`term_reference_tree` aren't installed. Before adding one, check it against
WCAG 2.2 AA and ATAG 2.0. Fixing the existing widgets is likely the smaller
change.

### 5. Search and facets

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| All six search indexes (database and Solr) store term fields as **names**, not term IDs (for example `field_category:entity:name`, `search_api.index.mukurtu_default_content_index.yml:280`). | Facet hierarchy needs term IDs. Same-named terms merge into one facet value. | Add a term ID field for each faceted vocabulary. | L |
| No index enables the Search API `hierarchy` processor. `TaxonomyTermAggregates` indexes directly referenced terms only (`TaxonomyTermAggregates.php:149-170`). | Selecting or searching a parent doesn't find content tagged with its children. | Enable the `hierarchy` processor on the new term ID fields, and add an "include ancestors" option to the aggregates processor. | M |
| All 28 facets ship with `use_hierarchy: false`. Several browse and dictionary views show `hierarchy: true`, but that's a side effect of the `facets_exposed_filters` module turning it on whenever `hierarchy_processor` is listed. | Neither path nests anything today. | Point the facets at the term ID fields, turn on `use_hierarchy`, and add the entity label processor so labels show (with translation). Decide on expand and keep-parents-active behavior. | M |
| `mukurtu_search_rebuild_index()` (`mukurtu_search.module:107-198`) keeps only shipped fields on the auto index and re-adds a fixed list of name fields. | New ID fields would be stripped on rebuild. The database index is already close to the 64-key MySQL limit (#2088). | Update the rebuild function. Measure the key budget before adding fields to the database index. | M |
| The theme's checkbox facet template (`facets-item-list--checkbox.html.twig:43-62`) wraps each list in `<details>` with the facet title as `<summary>`. Facets renders child lists with the same template. **(spot-checked)** | Each nested level becomes a `<details>` with no summary, which browsers label "Details." This is an accessibility defect. | Render child lists without `<details>`. Add indentation, an expand control with `aria-expanded`, and group labelling. Same for the dropdown template. | M |
| `js/facets-soft-limit.js` hides items with `find('li')` (line 46) but counts and shows them with `find('> li')` (lines 55, 73-79). **(spot-checked)** | With nesting, child items get hidden and never shown again. | Count and toggle top-level items only. | S |

### 6. Views and listings

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| `views.view.mukurtu_categories.yml` filters on `parent_target_id <= 0` (`:141-160`). **(spot-checked)** | The categories page and block (used on landing pages) already show only top-level categories. Child categories can't be reached from there. | Intended behavior, as long as the category page then lists child categories and includes their content (areas 2 and 3). | S |
| The manage all content view (`views.view.mukurtu_manage_all_content.yml:881-969`) has Category and Keywords filters with `hierarchy: false`. | The dropdown is flat, and filtering by a parent leaves out its children. | Set `hierarchy: true` and use the depth filter. | S |
| `taxonomy_index_tid_depth` isn't used anywhere. | See areas 2 and 6. | See above. | n/a |

### 7. Import and export

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| Each vocabulary's import strategy maps a `Parent` column. The value is resolved as a term ID, UUID, or unique name (`EntityReference.php:84-196`, `MukurtuEntityLookup.php:41-115`). | Works, but a name that matches several terms fails the row as ambiguous, and a number-only name (for example "1999") is read as a term ID. | Document that UUID is the reliable form. | S |
| Each CSV is imported in one pass, in file order. The parent lookup only checks terms already saved. | **A child row that comes before its parent row silently becomes a top-level term.** | Sort rows so parents come first, run a second pass, or use a self-referencing lookup with stubs. | M |
| Content imports that create terms by name pick the first match (`MukurtuEntityGenerate`) and always create at the top level. | Same-named terms are picked silently and at random. | Document that term ID or UUID is required for nested vocabularies. | S |
| Exporters write `parent` as a name, joined with `;` (`CsvEntityFieldExportEventSubscriber.php:250-302`). Taxonomy import strategies split on `'||'`. **(spot-checked)** | **A term with two parents doesn't round-trip.** "A;B" is read as one name, isn't found, and the term becomes top level. | Use the same delimiter in both directions. | S |
| Export writes rows in term ID order (`TaxonomyExportStartForm.php:62-70`). | Parents aren't guaranteed to come before children, which feeds the ordering issue above. | Export in tree order. | S |
| Vocabularies that administrators create have no import strategy. | They need a manually built mapping. | Out of scope for hierarchy. | n/a |

### 8. v3 migration

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| All 13 `mukurtu_cms_v3_terms_*` migrations look up the parent in `d7_taxonomy_term` (for example `..._terms_category.yml:45-46`). **(spot-checked)** Mukurtu never runs that migration, so the lookup always returns nothing and the parent falls back to 0. | **Every migrated v3 term becomes top level, with no error.** This is a hidden bug today, masked because `flat_taxonomy` would reset the parent anyway. | Term IDs are kept during migration, so map `parent` directly with `sub_process`, or look up against the vocabulary's own migration with stubs. | M |
| The source passes all parent IDs as one array without `sub_process`. | Terms with multiple parents can't map correctly. | Covered by the fix above. | n/a |
| There are no v3 term migrations for `community_type`, `location`, or `place_type`. | Unrelated to hierarchy. | Confirm whether that's intended. | n/a |

Open question: did v3 sites actually use nested terms? If not, this is low
priority.

### 9. Multilingual

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| All 16 vocabularies are translatable. Core's `parent` field isn't translatable, so the hierarchy is shared across languages. | None. | n/a | n/a |
| Term labels are translated on the term page, in formatters, and in core's breadcrumb. | Any new parent trail or child list has to translate every term. | Use `EntityTranslationResolver` (`mukurtu_core/src/Service/EntityTranslationResolver.php`) and add the `languages:language_content` cache context. | S |
| `docs/content-language-policy.md` has no rules for taxonomy. | n/a | Add a short section about hierarchy and translated term labels. | S |

### 10. Access and protocols

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| Terms aren't access-controlled by protocol or community. Viewing a term uses core's "access content" permission. | None. | n/a | n/a |
| The term page view respects node access, and `taxonomy_index` only holds published content. | A depth argument keeps the same node access check, so content under protocols stays hidden. | No change needed. Add a test. | S |
| Core's breadcrumb doesn't check access on ancestor terms. | An unpublished parent's name could show. | See area 2. | S |

### 11. Other features that use terms

| Current state | Gap | Proposed fix | Size |
|---|---|---|---|
| The term path pattern is `[term:vocabulary:name]/[term:name]`. **(spot-checked)** | Same-named children get `-0` or `-1` suffixes, and the URL has no parent path. | Decide whether URLs should include the parent path (for example `[term:parents:join-path]`). Changing the pattern affects existing URLs, so that needs a decision first. | S |
| Content warnings match the configured media tag by exact term ID (`MukurtuMediaContentWarnings.php:184-205`). **(spot-checked)** | A warning on a parent tag doesn't apply to media tagged with a child. | Decide whether warnings should apply to child tags. If so, compare against the tag and its ancestors. | S |
| The media tag filter and the content warning settings form list tags flat, with untranslated labels (`MediaTagFilter.php:41-47`, `MukurtuContentWarningsSettingsForm.php:58-72`). | No nesting and no translation. | Show nesting and translated labels. | S |
| Person and Place related content matches exact term IDs (`RelatedContentComputationSubscriber.php:101-125`). | Children aren't included. | Probably correct as is. Confirm. | n/a |
| Local Contexts, notifications, community records, and landing page code don't use terms directly. | None. | n/a | n/a |

## Data-loss risks

Turning on hierarchy adds these risks. Each needs a fix or a decision before
release:

1. **Deleting a parent deletes its children.** Core deletes child terms that
   are left with no parent (`core/modules/taxonomy/src/Entity/Term.php:105-131`).
   Mukurtu's Category delete guard (`mukurtu_taxonomy.module:187-208`) only
   counts content tagged with the term itself. Deleting a parent category
   could delete child categories that Digital Heritage items need for their
   required Category field. Fix: extend the guard to descendants, or move
   children up a level before deleting.
2. **Merging a parent deletes its children.** The Term Merge module deletes
   merged terms, and the reference updater skips `parent` fields. Fix: move
   children to the target term before merging.
3. **Turning flat back on erases the hierarchy.** See area 1.
4. **Silent flattening on import and migration.** See areas 7 and 8.

## Open questions for the team

1. Should content pages show only the term name, or the full path?
2. Should selecting a parent in a facet, or visiting a parent's term page,
   include content tagged with its children? The rest of this audit assumes
   yes.
3. Should content warnings on a parent media tag also apply to its children?
4. Should term URLs include the parent path? Changing this affects existing
   URLs.
5. Should the stock vocabularies (especially Category and Location) ship
   with hierarchy turned on, or stay flat until an administrator opts in?
6. Did v3 sites use nested terms? This sets the priority of the migration
   fix.
7. Is a hierarchy-specific widget module wanted, or should the existing
   widgets be improved?

## Suggested sequence of pull requests

Each step can be reviewed and tested on its own. Steps 1 and 2 must land
together or in order, because step 1 makes nesting possible and step 2
removes the data-loss risks.

1. **Opt-in and admin (S to M).** Remove the checkbox lock, show Relations
   on non-flat vocabularies, add a confirmation step before flattening,
   update the import help text, and update the flat-guard tests.
2. **Data safety (M).** Delete and merge guards for parent terms, the
   export and import delimiter fix, and parent-first ordering on import and
   export.
3. **Term pages and listings (M).** Depth argument, child term list,
   breadcrumb access, and manage-content filters.
4. **Search and facets (L).** Term ID index fields, the hierarchy processor,
   facet configuration, the rebuild function, and the facet template and
   soft-limit fixes.
5. **Widgets and content display (M).** Tagify translation, nested options
   without dashes, and the combined label formatter fix.
6. **v3 migration (M).** Parent mapping fix, if v3 sites used nesting.

Every step needs Kernel tests. Steps 3 to 5 need accessibility checks.
Step 4 likely needs an update hook for existing indexes and facets. Check
`docs/update-hooks.md` for each step.

## Proposed user-facing text

These are drafts for review. None of them are in the code.

| Where | Current text | Draft |
|---|---|---|
| Vocabulary form, **Flat taxonomy** description | "Mukurtu does not currently support nested/hierarchical taxonomies." | "If checked, terms in this vocabulary can't be nested. Checking this on a vocabulary that has nested terms moves every term to the top level." |
| Import field description for `parent` | "Not currently supported in Mukurtu. Used if a site supports hierarchical taxonomies." | "The parent term, as a UUID, term ID, or unique name. Leave blank for a top-level term. Only used if the vocabulary allows nested terms." |
