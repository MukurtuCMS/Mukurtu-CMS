# Description

This module provides some of the default browse pages in Mukurtu CMS including:
* Browse
* Browse Digital Heritage

All the default browse pages have support for both the SAPI DB and SOLR backends,
using the default Mukurtu CMS SAPI indexes.

Also in this module is `MediaTypeIndexingItemsSubscriber` which during SAPI
index events converts media asset bundle fields to their corresponding label.
