<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_taxonomy_update_40021().
 *
 * The taxonomy term page facets never filtered anything once referenced
 * content moved to the core taxonomy_term view. The hook deletes the six
 * orphaned facets.facet.* config objects (database + Solr variants) on
 * existing sites. This seeds those config objects, runs the hook, and
 * confirms they are gone - and that a second run is a no-op.
 *
 * @see \mukurtu_taxonomy_update_40021()
 * @group mukurtu_taxonomy
 */
class TaxonomyReferenceFacetRemovalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   *
   * The seeded stubs are not full facets.facet.* config; skip schema checks.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The orphaned facet config names the hook removes.
   */
  private const FACET_CONFIG_NAMES = [
    'facets.facet.taxonomy_reference_category',
    'facets.facet.taxonomy_reference_keywords',
    'facets.facet.taxonomy_reference_community',
    'facets.facet.taxonomy_reference_category_solr',
    'facets.facet.taxonomy_reference_keywords_solr',
    'facets.facet.taxonomy_reference_community_solr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config_factory = \Drupal::configFactory();
    foreach (self::FACET_CONFIG_NAMES as $name) {
      $config_factory->getEditable($name)
        ->set('id', substr($name, strlen('facets.facet.')))
        ->set('status', TRUE)
        ->save();
    }

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/mukurtu_taxonomy.install';
  }

  /**
   * The hook deletes every orphaned facet config object.
   */
  public function testRemovesOrphanedFacetConfig(): void {
    foreach (self::FACET_CONFIG_NAMES as $name) {
      $this->assertFalse($this->config($name)->isNew(), "$name should exist before the hook runs.");
    }

    mukurtu_taxonomy_update_40021();

    foreach (self::FACET_CONFIG_NAMES as $name) {
      $this->assertTrue($this->config($name)->isNew(), "$name should be deleted after the hook runs.");
    }
  }

  /**
   * Running the hook again when the config is already gone does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_taxonomy_update_40021();
    mukurtu_taxonomy_update_40021();

    foreach (self::FACET_CONFIG_NAMES as $name) {
      $this->assertTrue($this->config($name)->isNew(), "$name should remain deleted.");
    }
  }

}
