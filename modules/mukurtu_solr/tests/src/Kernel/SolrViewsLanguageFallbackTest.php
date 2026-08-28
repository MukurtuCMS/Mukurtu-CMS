<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_solr\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_solr_update_40005().
 *
 * Loads each view's real shipped config, strips the language_with_fallback
 * filter the hook adds (and, where the view previously had one, restores
 * the old search_api_language filter it replaced) to simulate a site that
 * installed before this fix, runs the hook, and confirms it converges -
 * and that running it twice is a no-op.
 *
 * @see \mukurtu_solr_update_40005()
 * @group mukurtu_solr
 */
class SolrViewsLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'search_api'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_solr') . '/config/install');

    $data_dictionary_browse_solr_new_index = $source->read('views.view.dictionary_browse_solr_new_index');
    $lwf_dictionary_browse_solr_new_index = $data_dictionary_browse_solr_new_index['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_dictionary_browse_solr_new_index['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.dictionary_browse_solr_new_index')->setData($data_dictionary_browse_solr_new_index)->save();

    $data_mukurtu_browse_by_map_solr = $source->read('views.view.mukurtu_browse_by_map_solr');
    $lwf_mukurtu_browse_by_map_solr = $data_mukurtu_browse_by_map_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_by_map_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_by_map_solr')->setData($data_mukurtu_browse_by_map_solr)->save();

    $data_mukurtu_browse_collections_solr = $source->read('views.view.mukurtu_browse_collections_solr');
    $lwf_mukurtu_browse_collections_solr = $data_mukurtu_browse_collections_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_collections_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_collections_solr')->setData($data_mukurtu_browse_collections_solr)->save();

    $data_mukurtu_browse_solr = $source->read('views.view.mukurtu_browse_solr');
    $lwf_mukurtu_browse_solr = $data_mukurtu_browse_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_solr')->setData($data_mukurtu_browse_solr)->save();

    $data_mukurtu_dictionary_solr = $source->read('views.view.mukurtu_dictionary_solr');
    $lwf_mukurtu_dictionary_solr = $data_mukurtu_dictionary_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_dictionary_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_dictionary_solr')->setData($data_mukurtu_dictionary_solr)->save();

    $data_mukurtu_digital_heritage_browse_solr = $source->read('views.view.mukurtu_digital_heritage_browse_solr');
    $lwf_mukurtu_digital_heritage_browse_solr = $data_mukurtu_digital_heritage_browse_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_digital_heritage_browse_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_digital_heritage_browse_solr')->setData($data_mukurtu_digital_heritage_browse_solr)->save();

    $data_mukurtu_taxonomy_references_solr = $source->read('views.view.mukurtu_taxonomy_references_solr');
    $lwf_mukurtu_taxonomy_references_solr = $data_mukurtu_taxonomy_references_solr['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_taxonomy_references_solr['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_taxonomy_references_solr')->setData($data_mukurtu_taxonomy_references_solr)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_solr') . '/mukurtu_solr.install';
  }

  /**
   * The hook adds the filter when missing.
   */
  public function testConverges(): void {
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.dictionary_browse_solr_new_index')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map_solr')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections_solr')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_solr')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary_solr')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse_solr')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references_solr')->get('display.default.display_options.filters') ?? []);

    mukurtu_solr_update_40005();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.dictionary_browse_solr_new_index')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references_solr')->get('display.default.display_options.filters'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_solr_update_40005();
    mukurtu_solr_update_40005();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.dictionary_browse_solr_new_index')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse_solr')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references_solr')->get('display.default.display_options.filters'));
  }

}
