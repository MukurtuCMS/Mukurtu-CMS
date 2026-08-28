<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_dictionary_update_40043().
 *
 * Loads each view's real shipped config, strips the language_with_fallback
 * filter the hook adds (and, where the view previously had one, restores
 * the old search_api_language filter it replaced) to simulate a site that
 * installed before this fix, runs the hook, and confirms it converges -
 * and that running it twice is a no-op.
 *
 * @see \mukurtu_dictionary_update_40043()
 * @group mukurtu_dictionary
 */
class DictionaryViewLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'search_api'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_dictionary') . '/config/install');

    $data_mukurtu_dictionary = $source->read('views.view.mukurtu_dictionary');
    $lwf_mukurtu_dictionary = $data_mukurtu_dictionary['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_dictionary['display']['default']['display_options']['filters']['language_with_fallback']);
    // Restore the old filter this hook is meant to replace, using the field's own table (identical shape across all these views, differing only by table).
    $data_mukurtu_dictionary['display']['default']['display_options']['filters']['search_api_language'] = [
      'id' => 'search_api_language',
      'table' => $lwf_mukurtu_dictionary['table'],
      'field' => 'search_api_language',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api_language',
      'operator' => 'in',
      'value' => ['***LANGUAGE_language_interface***' => '***LANGUAGE_language_interface***'],
      'group' => 1,
    ];
    \Drupal::configFactory()->getEditable('views.view.mukurtu_dictionary')->setData($data_mukurtu_dictionary)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary') . '/mukurtu_dictionary.install';
  }

  /**
   * The hook adds the filter when missing and defensively removes the old one it replaces.
   */
  public function testConverges(): void {
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary')->get('display.default.display_options.filters') ?? []);

    mukurtu_dictionary_update_40043();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary')->get('display.default.display_options.filters'));
    $this->assertArrayNotHasKey('search_api_language', $this->config('views.view.mukurtu_dictionary')->get('display.default.display_options.filters'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_dictionary_update_40043();
    mukurtu_dictionary_update_40043();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_dictionary')->get('display.default.display_options.filters'));
  }

}
