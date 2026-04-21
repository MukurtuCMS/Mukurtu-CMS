<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Search API Solr event base class.
 */
abstract class AbstractServerAwareEvent extends Event {

  /**
   * The lucene match version string.
   *
   * @var string
   */
  protected $luceneMatchVersion;

  /**
   * The server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * Constructs a new class instance.
   *
   * @param string $lucene_match_version
   *   The lucene match version string.
   * @param string $server_id
   *   The server ID.
   */
  public function __construct(string $lucene_match_version, string $server_id) {
    $this->luceneMatchVersion = $lucene_match_version;
    $this->serverId = $server_id;
  }

  /**
   * Retrieves the lucene match version.
   */
  public function getLuceneMatchVersion(): string {
    return $this->luceneMatchVersion;
  }

  /**
   * Retrieves the server ID.
   */
  public function getServerId(): string {
    return $this->serverId;
  }

}
