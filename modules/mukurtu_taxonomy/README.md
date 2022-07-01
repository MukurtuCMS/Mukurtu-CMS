# Description

The Mukurtu Taxonomy module provides pages to manage the default Mukurtu
taxonomy vocabularies as well as "taxonomy records".

## Taxonomy Records
Taxonomy records create a relationship between individual content entities and
taxonomy terms. The content entities will be shown in place of the taxonomy term
when visiting the canonical taxonomy entity path.

### Enabling a Content Type for Taxonomy Records
Only content entities (nodes) can be used as taxonomy records. To enable a given
content type, add the 'field_representative_terms' field to the content bundle.

### Enabling a Taxonomy Vocabulary for Taxonomy Records
The vocabularies enabled for taxonomy records can be altered at the config page
found at `/admin/config/mukurtu/taxonomy/records`.
