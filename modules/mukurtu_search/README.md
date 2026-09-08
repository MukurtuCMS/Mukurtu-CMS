# Description
This module provides the base configuration and functionality for Search API (SAPI) in Mukurtu CMS. Many search specifics will be found in their respective modules (e.g., the dictionary module contains dictionary specific SAPI config/views).

## Settings
Route `mukurtu_search.settings` allows site admins to select between two Mukurtu CMS search backends, database and Solr. The Solr backend requires configuration on the part of the end-user, to reflect their specific Solr setup. The database should "just work" out of the box.

The database SAPI backend works well for smaller sites, with performance really starting to suffer at around 1000-2000+ indexed items. The database backend adds one MySQL/MariaDB index per indexed field, and a table is limited to 64 indexes, so a database SAPI index can carry at most about 63 fields.

## Events
This module provides the `FieldAvailableForIndexing` event class that has two events, one for new fields available for index and another for updated fields available for index. These are provided so that other modules can react to site specific entity changes and modify indexed fields on Mukurtu CMS SAPI indexes as appropriate, reducing the amount of site administration end-users need to do to manage.

On the database backend, subscribers should target Solr indexes only. `mukurtu_browse_auto_index` (see below) now carries a fixed field set, so per-field additions there would risk the 64-index limit.

## Search Indexes
This module provides two SAPI indexes:

#### Mukurtu Browse Auto Content Index (`mukurtu_browse_auto_index`)
This is a database backed SAPI index. Its field set is fixed: everything it needs is declared in this module's `config/install`, and `mukurtu_search_rebuild_index()` restores it to that state (stripping any per-field taxonomy variants left by older releases).

Rather than adding a `__name__text` and `__uuid` field for every taxonomy reference field on every content type (which overflows the 64-index limit once a site has content-type-specific vocabularies), the `taxonomy_term_aggregates` processor exposes two index-wide fields, `all_taxonomy_term_names` (fulltext) and `all_taxonomy_term_uuids` (string), covering every referenced term with no per-field database key. New content types and taxonomy reference fields are picked up automatically. The equivalent per-field Solr fields are still added by `TaxonomyFieldSearchIndexSubscriber` and `SolrBaseFieldsSearchIndexSubscriber` on `mukurtu_default_solr_index`, which has no field limit.


### Mukurtu Default Solr Content Index (`mukurtu_default_solr_index`)

The Solr backend does not have a hard limit on the number of indexed fields like the database backend, so it is fine to be a bit more generous with the number of indexed fields. In testing, we have achieved acceptable response times for sites with ~250,000 items using the Solr backend.
