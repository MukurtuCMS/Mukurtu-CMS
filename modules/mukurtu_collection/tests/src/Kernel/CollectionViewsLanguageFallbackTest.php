<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_collection_update_40027().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_collection_update_40027()
 * @group mukurtu_collection
 */
class CollectionViewsLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'mukurtu_collection', 'geofield', 'leaflet_views', 'mukurtu_core', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_collection') . '/config/install');

    $data_mukurtu_collection_items = $source->read('views.view.mukurtu_collection_items');
    unset($data_mukurtu_collection_items['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_collection_items['display']['mukurtu_collection_items_block']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_collection_items')->setData($data_mukurtu_collection_items)->save();

    $data_my_personal_collections = $source->read('views.view.my_personal_collections');
    unset($data_my_personal_collections['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_my_personal_collections['display']['my_personal_collections_block']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.my_personal_collections')->setData($data_my_personal_collections)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_collection') . '/mukurtu_collection.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_collection_items')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.my_personal_collections')->get('display.default.display_options.filters') ?? []);

    mukurtu_collection_update_40027();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_collection_items')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_collection_items')->get('display.mukurtu_collection_items_block.display_options.rendering_language'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.my_personal_collections')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.my_personal_collections')->get('display.my_personal_collections_block.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_collection_update_40027();
    mukurtu_collection_update_40027();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_collection_items')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.my_personal_collections')->get('display.default.display_options.filters'));
  }

}
