# Description

The Mukurtu Taxonomy module provides pages to manage the default Mukurtu
taxonomy vocabularies as well as "taxonomy records".

## Taxonomy Records
Taxonomy records create a relationship between individual content entities and
a specific taxonomy term. The content entities will be shown in place of the taxonomy term
when visiting the canonical taxonomy entity path. For example, if you have a
taxonomy term "Alice" in the "People" vocabulary, one or more "Person" nodes
(or any other mix of enabled node bundle types) could be added as taxonomy
records and would be shown in lieu of the term on the term's canonical view page.

Display of taxonomy records is handled in `TaxonomyRecordViewController` and
uses the `taxonomy-records.html.twig` template.

In a normal default Drupal taxonomy term display, content that uses that term is displayed as "referenced content". This is implemented with a view. Replicating this behavior and adding search/facets when taxonomy records are enabled is too complex for views, and is dependant on querying SAPI for the taxonomy term's UUID, see `TaxonomyFieldSearchIndexSubscriber` for specifics.

### Enabling a Content Type for Taxonomy Records
Only content entities (nodes) can be used as taxonomy records. To enable a given
content type, add the 'field_other_names' field to the content bundle.

### Enabling a Taxonomy Vocabulary for Taxonomy Records
The vocabularies enabled for person records can be altered at the config page
found at `/admin/config/mukurtu/person-records` (route `mukurtu_taxonomy.person_record_settings`).
