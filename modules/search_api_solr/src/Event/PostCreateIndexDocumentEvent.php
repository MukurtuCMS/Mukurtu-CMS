<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired after a solarium document has been created for indexing.
 *
 * @code
 *   // Add a "foo" field with value "bar" to the document.
 *   $document = $event->getSolariumDocument();
 *   $document->setField('foo', 'bar');
 * @endcode
 */
final class PostCreateIndexDocumentEvent extends AbstractSearchApiItemSolariumDocumentEvent {}
