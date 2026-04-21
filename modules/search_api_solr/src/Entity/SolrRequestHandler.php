<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\search_api_solr\SolrRequestHandlerInterface;

/**
 * Defines the SolrRequestHandler entity.
 *
 * @ConfigEntityType(
 *   id = "solr_request_handler",
 *   label = @Translation("Solr Request Handler"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr\Controller\SolrRequestHandlerListBuilder",
 *     "form" = {
 *     }
 *   },
 *   config_prefix = "solr_request_handler",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "disabled" = "disabled_request_handlers"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "minimum_solr_version",
 *     "environments",
 *     "recommended",
 *     "request_handler",
 *     "solr_configs"
 *   },
 *   links = {
 *     "disable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_handler/{solr_request_handler}/disable",
 *     "enable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_handler/{solr_request_handler}/enable",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/solr_request_handler"
 *   }
 * )
 */
class SolrRequestHandler extends AbstractSolrEntity implements SolrRequestHandlerInterface {

  /**
   * Solr custom request handler definition.
   *
   * @var array
   */
  protected $request_handler;

  /**
   * The targeted environments.
   *
   * @var string[]
   */
  protected $environments;

  /**
   * {@inheritdoc}
   */
  public function getRequestHandler() {
    return $this->request_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    $nested_name = $this->request_handler['lst'][0]['name'] ?? 'default';
    return $this->request_handler['name'] . '_' . $nested_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurposeId(): string {
    return $this->request_handler['name'];
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
    return parent::getAvailableOptions('environments', 'default', 'search_api_solr.solr_request_handler.');
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

    $formatted_xml_string = $this->buildXmlFromArray('requestHandler', $this->request_handler);

    return $comment . $formatted_xml_string . "\n";
  }

}
