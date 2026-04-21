<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired after all solarium documents
 * have been created for indexing.
 *
 * @code
 *   // Add a "foo" field with value "bar" to all documents.
 *   $documents = $event->getSolariumDocuments();
 *   foreach ($documents as $document) {
 *     $document->setField('foo', 'bar');
 *   }
 *   $event->setSolariumDocuments($documents):
 * @endcode
 *
 * @Event
 */
final class PostCreateIndexDocumentsEvent extends AbstractSearchApiItemsSolariumDocumentsEvent {}
