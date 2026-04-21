<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\search_api_solr\SolrRequestDispatcherInterface;

/**
 * Defines the SolrRequestDispatcher entity.
 *
 * @ConfigEntityType(
 *   id = "solr_request_dispatcher",
 *   label = @Translation("Solr Request Dispatcher"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr\Controller\SolrRequestDispatcherListBuilder",
 *     "form" = {
 *     }
 *   },
 *   config_prefix = "solr_request_dispatcher",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "disabled" = "disabled_request_dispatchers"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "minimum_solr_version",
 *     "environments",
 *     "recommended",
 *     "request_dispatcher"
 *   },
 *   links = {
 *     "disable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_dispatcher/{solr_request_dispatcher}/disable",
 *     "enable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_dispatcher/{solr_request_dispatcher}/enable",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_dispatcher"
 *   }
 * )
 */
class SolrRequestDispatcher extends AbstractSolrEntity implements SolrRequestDispatcherInterface {

  /**
   * Solr custom request_dispatcher definition.
   *
   * @var array
   */
  protected $request_dispatcher;

  /**
   * The targeted environments.
   *
   * @var string[]
   */
  protected $environments;

  /**
   * {@inheritdoc}
   */
  public function getRequestDispatcher() {
    return $this->request_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->request_dispatcher['name'] . '_' . $this->isRecommended();
  }

  /**
   * {@inheritdoc}
   */
  public function getPurposeId(): string {
    return $this->request_dispatcher['name'];
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
    return parent::getAvailableOptions('environments', 'default', 'search_api_solr.solr_request_dispatcher.');
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

    $copy = $this->request_dispatcher;
    $root = $this->request_dispatcher['name'];
    unset($copy['name']);

    $formatted_xml_string = $this->buildXmlFromArray($root, $copy);

    return $comment . $formatted_xml_string . "\n";
  }

}
