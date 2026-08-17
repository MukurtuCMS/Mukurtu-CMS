<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

/**
 * Builds links to the browse pages pre-filtered by a facet value.
 */
class BrowseLinkBuilder {

  /**
   * Constructs a new BrowseLinkBuilder object.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the search backend in use ('db' or 'solr').
   *
   * @return string
   *   The search backend.
   */
  protected function getBackend(): string {
    return $this->configFactory->get('mukurtu_search.settings')->get('backend') ?? 'db';
  }

  /**
   * Builds a link to /browse filtered to a single community.
   *
   * @param string $communityName
   *   The community's name, as stored on the community entity.
   *
   * @return \Drupal\Core\Url
   *   The browse URL, pre-filtered to the given community.
   */
  public function getCommunityBrowseUrl(string $communityName): Url {
    return $this->buildFacetUrl('mukurtu_browse.browse_page', 'community_title', 'browse_solr_community', $communityName);
  }

  /**
   * Builds a link to /digital-heritage filtered to a single category.
   *
   * @param string $categoryName
   *   The category term's name.
   *
   * @return \Drupal\Core\Url
   *   The digital heritage browse URL, pre-filtered to the given category.
   */
  public function getCategoryBrowseUrl(string $categoryName): Url {
    return $this->buildFacetUrl('mukurtu_browse.browse_digital_heritage_page', 'category', 'digital_heritage_browse_solr_category', $categoryName);
  }

  /**
   * Builds a link to /browse filtered to a single cultural protocol.
   *
   * There is currently no Cultural Protocol facet on the Solr-backed browse
   * page, so on that backend this falls back to a plain, unfiltered link.
   *
   * @param string $protocolId
   *   The protocol's entity ID.
   *
   * @return \Drupal\Core\Url
   *   The browse URL, pre-filtered to the given protocol where supported.
   */
  public function getProtocolBrowseUrl(string $protocolId): Url {
    return $this->buildFacetUrl('mukurtu_browse.browse_page', 'cultural_protocols', NULL, $protocolId);
  }

  /**
   * Builds a URL pre-filtered to a single facet value, for either backend.
   *
   * On the DB backend, filters are embedded Views exposed filters rendered
   * as a Checkboxes form element; Checkboxes::valueCallback() reads the
   * *keys* of the raw input as the selected values, so the query parameter
   * must be an associative array mapping the value to itself
   * (`identifier[value]=value`), matching what a browser submits when a
   * checkbox is actually checked. A bare scalar crashes form rendering
   * entirely, and a plain indexed array (`identifier[0]=value`) is silently
   * read as if the literal key "0" were selected. On the Solr
   * backend, filters are real Facets config entities read from the
   * `f[]=<url_alias>:<value>` query scheme; the facet's real url_alias is
   * looked up dynamically so this keeps working if a facet is reconfigured.
   *
   * @param string $routeName
   *   The route to link to.
   * @param string $dbIdentifier
   *   The DB backend's exposed filter identifier for this facet.
   * @param string|null $solrFacetId
   *   The Solr backend's facets_facet entity ID for this facet, or NULL if
   *   no such facet exists yet.
   * @param string $rawValue
   *   The raw filter value.
   *
   * @return \Drupal\Core\Url
   *   The built URL.
   */
  protected function buildFacetUrl(string $routeName, string $dbIdentifier, ?string $solrFacetId, string $rawValue): Url {
    $backend = $this->getBackend();

    if ($backend === 'solr' && $solrFacetId) {
      $facet = $this->entityTypeManager->getStorage('facets_facet')->load($solrFacetId);
      if ($facet) {
        return Url::fromRoute($routeName, [], [
          'query' => ['f' => [$facet->getUrlAlias() . ':' . $rawValue]],
        ]);
      }
    }

    if ($backend === 'solr') {
      return Url::fromRoute($routeName);
    }

    return Url::fromRoute($routeName, [], [
      'query' => [$dbIdentifier => [$rawValue => $rawValue]],
    ]);
  }

}
