<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired after a solarium document has been created for indexing.
 *
 * But before any search_api fields are populated on the document!
 */
final class PreCreateIndexDocumentEvent extends AbstractSearchApiItemSolariumDocumentEvent {}
