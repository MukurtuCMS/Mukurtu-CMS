<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_taxonomy_update_40019().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_taxonomy_update_40019()
 * @group mukurtu_taxonomy
 */
class TaxonomyTermViewLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/config/install');

    $data_taxonomy_term = $source->read('views.view.taxonomy_term');
    unset($data_taxonomy_term['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_taxonomy_term['display']['page_1']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.taxonomy_term')->setData($data_taxonomy_term)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/mukurtu_taxonomy.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.taxonomy_term')->get('display.default.display_options.filters') ?? []);

    mukurtu_taxonomy_update_40019();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.taxonomy_term')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.taxonomy_term')->get('display.page_1.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_taxonomy_update_40019();
    mukurtu_taxonomy_update_40019();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.taxonomy_term')->get('display.default.display_options.filters'));
  }

}
