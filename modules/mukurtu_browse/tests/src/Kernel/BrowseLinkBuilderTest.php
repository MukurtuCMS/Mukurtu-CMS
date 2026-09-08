<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\facets\Entity\Facet;
use Drupal\mukurtu_browse\Service\BrowseLinkBuilder;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests BrowseLinkBuilder, which links to /browse and /digital-heritage
 * pre-filtered by community, category or cultural protocol.
 */
#[Group('mukurtu_browse')]
class BrowseLinkBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facets',
    'mukurtu_browse',
    'mukurtu_search',
  ];

  /**
   * The service under test.
   */
  protected BrowseLinkBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Deliberately not installing mukurtu_search's shipped default config:
    // it ships a node view mode that requires the 'node' module to be
    // enabled, which this test doesn't need. Its config schema (needed to
    // validate mukurtu_search.settings below) is available purely from the
    // module being enabled, independent of installing its default config.

    // Install the real Solr facet configs this service depends on, without
    // requiring the full mukurtu_solr module (and its search_api_solr
    // dependency chain) to be enabled. The module extension list can locate
    // mukurtu_solr's files even though it's never installed/enabled here,
    // since extension discovery scans the codebase, not just enabled
    // modules.
    $install_path = \Drupal::service('extension.list.module')->getPath('mukurtu_solr') . '/config/install/';
    foreach (['browse_solr_community', 'digital_heritage_browse_solr_category'] as $facet_id) {
      $data = Yaml::parseFile($install_path . "facets.facet.$facet_id.yml");
      Facet::create($data)->save();
    }

    $this->builder = \Drupal::service('mukurtu_browse.browse_link_builder');
  }

  /**
   * Sets the active search backend.
   */
  protected function setBackend(string $backend): void {
    $this->config('mukurtu_search.settings')->set('backend', $backend)->save();
  }

  /**
   * Extracts the decoded query parameters from a URL object.
   */
  protected function getQuery(Url $url): array {
    parse_str((string) parse_url($url->toString(), PHP_URL_QUERY), $query);
    return $query;
  }

  public function testCommunityUrlOnDbBackend(): void {
    $this->setBackend('db');
    $url = $this->builder->getCommunityBrowseUrl('California City');
    $this->assertStringContainsString('/browse', $url->toString());
    // The exposed filter renders as a Checkboxes element, whose
    // valueCallback() reads the raw input's *keys* as the selected values -
    // so the query parameter must map the value to itself, not just wrap it
    // in an indexed array (which would select the literal key "0" instead).
    $this->assertSame(['California City' => 'California City'], $this->getQuery($url)['community_title'] ?? NULL);
  }

  public function testCommunityUrlOnSolrBackend(): void {
    $this->setBackend('solr');
    $url = $this->builder->getCommunityBrowseUrl('California City');
    $this->assertStringContainsString('/browse', $url->toString());
    $this->assertSame(['browse_solr_community:California City'], $this->getQuery($url)['f'] ?? NULL);
  }

  public function testCategoryUrlOnDbBackend(): void {
    $this->setBackend('db');
    $url = $this->builder->getCategoryBrowseUrl('Places');
    $this->assertStringContainsString('/digital-heritage', $url->toString());
    $this->assertSame(['Places' => 'Places'], $this->getQuery($url)['category'] ?? NULL);
  }

  public function testCategoryUrlOnSolrBackend(): void {
    $this->setBackend('solr');
    $url = $this->builder->getCategoryBrowseUrl('Places');
    $this->assertStringContainsString('/digital-heritage', $url->toString());
    $this->assertSame(['digital_heritage_browse_solr_category:Places'], $this->getQuery($url)['f'] ?? NULL);
  }

  public function testProtocolUrlOnDbBackend(): void {
    $this->setBackend('db');
    $url = $this->builder->getProtocolBrowseUrl('12');
    $this->assertStringContainsString('/browse', $url->toString());
    $this->assertSame(['12' => '12'], $this->getQuery($url)['cultural_protocols'] ?? NULL);
  }

  /**
   * There is no Cultural Protocol facet on the Solr browse page (yet), so
   * this must fall back to a plain, unfiltered link rather than sending a
   * facet key that doesn't exist.
   */
  public function testProtocolUrlOnSolrBackendFallsBackToUnfilteredLink(): void {
    $this->setBackend('solr');
    $url = $this->builder->getProtocolBrowseUrl('12');
    $this->assertSame([], $this->getQuery($url));
  }

  /**
   * If the facet ID this service looks up doesn't resolve to an actual
   * facets_facet entity (e.g. renamed or removed on the live site), this
   * must fall back to a plain, unfiltered link rather than erroring or
   * emitting an f[] key with no matching facet.
   */
  public function testCommunityUrlOnSolrBackendFallsBackWhenFacetMissing(): void {
    $this->setBackend('solr');
    Facet::load('browse_solr_community')->delete();
    $url = $this->builder->getCommunityBrowseUrl('California City');
    $this->assertSame([], $this->getQuery($url));
  }

}
