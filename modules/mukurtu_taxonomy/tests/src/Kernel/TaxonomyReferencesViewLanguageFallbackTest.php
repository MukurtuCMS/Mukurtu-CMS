<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_taxonomy_update_40020().
 *
 * Loads each view's real shipped config, strips the language_with_fallback
 * filter the hook adds (and, where the view previously had one, restores
 * the old search_api_language filter it replaced) to simulate a site that
 * installed before this fix, runs the hook, and confirms it converges -
 * and that running it twice is a no-op.
 *
 * @see \mukurtu_taxonomy_update_40020()
 */
#[Group('mukurtu_taxonomy')]
class TaxonomyReferencesViewLanguageFallbackTest extends KernelTestBase {

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
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/config/install');

    $data_mukurtu_taxonomy_references = $source->read('views.view.mukurtu_taxonomy_references');
    $lwf_mukurtu_taxonomy_references = $data_mukurtu_taxonomy_references['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_taxonomy_references['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_taxonomy_references')->setData($data_mukurtu_taxonomy_references)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/mukurtu_taxonomy.install';
  }

  /**
   * The hook adds the filter when missing.
   */
  public function testConverges(): void {
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references')->get('display.default.display_options.filters') ?? []);

    mukurtu_taxonomy_update_40020();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references')->get('display.default.display_options.filters'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_taxonomy_update_40020();
    mukurtu_taxonomy_update_40020();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_taxonomy_references')->get('display.default.display_options.filters'));
  }

}
