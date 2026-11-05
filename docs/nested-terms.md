# Nested terms

Taxonomy vocabularies can let terms nest under other terms (milestone 12, #1517). This page is for site builders and developers. It covers what changes when a vocabulary allows nesting.

## Turning nesting on and off

Every vocabulary is flat by default, including new ones. To allow nesting, edit the vocabulary and uncheck **Flat taxonomy** (#2519).

- **Making a vocabulary flat again:** this moves every nested term to the top level, after a confirmation step. Mukurtu saves the nesting first.
- **Allowing nesting again:** Mukurtu offers to restore the saved nesting.
- **One parent:** a term can have only one parent. This applies to the term form and to imports.

## Content forms

On content forms, term fields show nesting (#2523).

- **Checkbox and radio fields** (Category, Community type, dictionary Language):
  - Each term's nested terms appear right after it, indented, in a group named after it. Screen readers announce the group.
  - Labels have no depth dashes.
- **Autocomplete fields (Tagify):**
  - Suggestions are grouped under their direct parent.
  - Each group is labelled with the path from the top-level term, such as "Washington › Spokane", in the current language.
  - This relies on `patches/tagify-nested-groups.patch` until the Tagify module includes the change.
- **New terms always start at the top level.** A term created by typing a new name into an autocomplete field has no parent. To nest it, edit the term afterwards, or drag it under its parent on the vocabulary's term list.

## Term pages and search

- **Term pages** (#2521): a term's page:
  - Lists content tagged with the term or any term nested under it.
  - Lists its nested terms.
  - Links to the term it's nested under.
- **Browse and dictionary facets** (#2522, database search):
  - Term facets nest.
  - Choosing a parent includes content tagged with the terms nested under it.
  - A facet that a site adds on a term ID field nests automatically. A facet on a term name field stays flat.

## Deleting, merging, importing and exporting

- **Deleting a term** moves its nested terms up a level instead of deleting them (#2520).
- **Merging terms** moves the merged terms' nested terms under the target.
- **CSV export** writes terms parents first.
- **CSV import:**
  - A parent can be named on any row.
  - Name parents by UUID when two terms in the vocabulary share a name.
