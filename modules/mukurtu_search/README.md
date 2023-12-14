# Description
This module provides the base configuration and functionality for Search API (SAPI) in Mukurtu CMS. Many search specifics will be found in their respective modules (e.g., the dictionary module contains dictionary specific SAPI config/views).

## Settings
Route `mukurtu_search.settings` allows site admins to select between two Mukurtu CMS search backends, database and Solr. The Solr backend requires configuration on the part of the end-user, to reflect their specific Solr setup. The database should "just work" out of the box.

The database SAPI backend works well for smaller sites, with performance really starting to suffer at around 1000-2000+ indexed items. Indexes on the database backend are limited to 63 indexed fields per individual index.

## Events
This module provides the `FieldAvailableForIndexing` event class that has two events, one for new fields available for index and another for updated fields available for index. These are provided so that other modules can react to site specific entity changes and modify indexed fields on Mukurtu CMS SAPI indexes as appropriate, reducing the amount of site administration end-users need to do to manage.

## Search Indexes
This module provides two SAPI indexes:

#### Mukurtu Browse Auto Content Index (`mukurtu_browse_auto_index`)
This is a database backed SAPI index and is intended to be a primary target for `FieldAvailableForIndexing` event subscribers. This module implements one of them in `BaseFieldsSearchIndexSubscriber` which keeps important base Mukurtu CMS fields like title, created/changed times, communities, keywords, and categories indexed. Because of the 63 field limit on DB indexes, it is important to be aware of all modules responding to this event. It also means that there are many use specific (e.g., the dictionary) DB indexes in use in Mukurtu CMS.


### Mukurtu Default Solr Content Index (`mukurtu_default_solr_index`)

The Solr backend does not have a hard limit on the number of indexed fields like the database backend, so it is fine to be a bit more generous with the number of indexed fields. In testing, we have achieved acceptable response times for sites with ~250,000 items using the Solr backend.
