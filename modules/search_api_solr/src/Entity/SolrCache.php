<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\search_api_solr\SolrCacheInterface;

/**
 * Defines the SolrCache entity.
 *
 * @ConfigEntityType(
 *   id = "solr_cache",
 *   label = @Translation("Solr Cache"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr\Controller\SolrCacheListBuilder",
 *     "form" = {
 *     }
 *   },
 *   config_prefix = "solr_cache",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "disabled" = "disabled_caches"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "minimum_solr_version",
 *     "environments",
 *     "cache",
 *     "solr_configs"
 *   },
 *   links = {
 *     "disable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache/{solr_cache}/disable",
 *     "enable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache/{solr_cache}/enable",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache"
 *   }
 * )
 */
class SolrCache extends AbstractSolrEntity implements SolrCacheInterface {

  /**
   * Solr custom cache definition.
   *
   * @var array
   */
  protected $cache;

  /**
   * The targeted environments.
   *
   * @var string[]
   */
  protected $environments;

  /**
   * {@inheritdoc}
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->cache['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironments() {
    return empty($this->environments) ? ['default'] : $this->environments;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    return $this->getEnvironments();
  }

  /**
   * Get all available environments.
   *
   * @return string[]
   *   An array of environments as strings.
   */
  public static function getAvailableEnvironments() {
    return parent::getAvailableOptions('environments', 'default', 'search_api_solr.solr_cache.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAsXml(bool $add_comment = TRUE): string {
    $comment = '';
    if ($add_comment) {
      $comment = "<!--\n  " . $this->label() . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    $copy = $this->cache;
    $root = 'cache';
    switch ($this->cache['name']) {
      case 'filter':
      case 'queryResult':
      case 'document':
      case 'fieldValue':
        $root = $this->cache['name'] . 'Cache';
        unset($copy['name']);
        break;
    }

    $formatted_xml_string = $this->buildXmlFromArray($root, $copy);

    return $comment . $formatted_xml_string . "\n";
  }

}
