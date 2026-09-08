<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_browse_update_40020().
 *
 * Loads each view's real shipped config, strips the language_with_fallback
 * filter the hook adds (and, where the view previously had one, restores
 * the old search_api_language filter it replaced) to simulate a site that
 * installed before this fix, runs the hook, and confirms it converges -
 * and that running it twice is a no-op.
 *
 * @see \mukurtu_browse_update_40020()
 */
#[Group('mukurtu_browse')]
class BrowseViewsLanguageFallbackTest extends KernelTestBase {

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
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_browse') . '/config/install');

    $data_mukurtu_browse = $source->read('views.view.mukurtu_browse');
    $lwf_mukurtu_browse = $data_mukurtu_browse['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse['display']['default']['display_options']['filters']['language_with_fallback']);
    // Restore the old filter this hook is meant to replace, using the field's own table (identical shape across all these views, differing only by table).
    $data_mukurtu_browse['display']['default']['display_options']['filters']['search_api_language'] = [
      'id' => 'search_api_language',
      'table' => $lwf_mukurtu_browse['table'],
      'field' => 'search_api_language',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api_language',
      'operator' => 'in',
      'value' => ['***LANGUAGE_language_interface***' => '***LANGUAGE_language_interface***'],
      'group' => 1,
    ];
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse')->setData($data_mukurtu_browse)->save();

    $data_mukurtu_browse_collections = $source->read('views.view.mukurtu_browse_collections');
    $lwf_mukurtu_browse_collections = $data_mukurtu_browse_collections['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_collections['display']['default']['display_options']['filters']['language_with_fallback']);
    // Restore the old filter this hook is meant to replace, using the field's own table (identical shape across all these views, differing only by table).
    $data_mukurtu_browse_collections['display']['default']['display_options']['filters']['search_api_language'] = [
      'id' => 'search_api_language',
      'table' => $lwf_mukurtu_browse_collections['table'],
      'field' => 'search_api_language',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api_language',
      'operator' => 'in',
      'value' => ['***LANGUAGE_language_interface***' => '***LANGUAGE_language_interface***'],
      'group' => 1,
    ];
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_collections')->setData($data_mukurtu_browse_collections)->save();

    $data_mukurtu_browse_map = $source->read('views.view.mukurtu_browse_map');
    $lwf_mukurtu_browse_map = $data_mukurtu_browse_map['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_map['display']['default']['display_options']['filters']['language_with_fallback']);
    // Restore the old filter this hook is meant to replace, using the field's own table (identical shape across all these views, differing only by table).
    $data_mukurtu_browse_map['display']['default']['display_options']['filters']['search_api_language'] = [
      'id' => 'search_api_language',
      'table' => $lwf_mukurtu_browse_map['table'],
      'field' => 'search_api_language',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api_language',
      'operator' => 'in',
      'value' => ['***LANGUAGE_language_interface***' => '***LANGUAGE_language_interface***'],
      'group' => 1,
    ];
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_map')->setData($data_mukurtu_browse_map)->save();

    $data_mukurtu_digital_heritage_browse = $source->read('views.view.mukurtu_digital_heritage_browse');
    $lwf_mukurtu_digital_heritage_browse = $data_mukurtu_digital_heritage_browse['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_digital_heritage_browse['display']['default']['display_options']['filters']['language_with_fallback']);
    // Restore the old filter this hook is meant to replace, using the field's own table (identical shape across all these views, differing only by table).
    $data_mukurtu_digital_heritage_browse['display']['default']['display_options']['filters']['search_api_language'] = [
      'id' => 'search_api_language',
      'table' => $lwf_mukurtu_digital_heritage_browse['table'],
      'field' => 'search_api_language',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api_language',
      'operator' => 'in',
      'value' => ['***LANGUAGE_language_interface***' => '***LANGUAGE_language_interface***'],
      'group' => 1,
    ];
    \Drupal::configFactory()->getEditable('views.view.mukurtu_digital_heritage_browse')->setData($data_mukurtu_digital_heritage_browse)->save();

    $data_mukurtu_browse_by_map = $source->read('views.view.mukurtu_browse_by_map');
    $lwf_mukurtu_browse_by_map = $data_mukurtu_browse_by_map['display']['default']['display_options']['filters']['language_with_fallback'];
    unset($data_mukurtu_browse_by_map['display']['default']['display_options']['filters']['language_with_fallback']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse_by_map')->setData($data_mukurtu_browse_by_map)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_browse') . '/mukurtu_browse.install';
  }

  /**
   * The hook adds the filter when missing and defensively removes the old one it replaces.
   */
  public function testConverges(): void {
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_map')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map')->get('display.default.display_options.filters') ?? []);

    mukurtu_browse_update_40020();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse')->get('display.default.display_options.filters'));
    $this->assertArrayNotHasKey('search_api_language', $this->config('views.view.mukurtu_browse')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections')->get('display.default.display_options.filters'));
    $this->assertArrayNotHasKey('search_api_language', $this->config('views.view.mukurtu_browse_collections')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_map')->get('display.default.display_options.filters'));
    $this->assertArrayNotHasKey('search_api_language', $this->config('views.view.mukurtu_browse_map')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse')->get('display.default.display_options.filters'));
    $this->assertArrayNotHasKey('search_api_language', $this->config('views.view.mukurtu_digital_heritage_browse')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map')->get('display.default.display_options.filters'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_browse_update_40020();
    mukurtu_browse_update_40020();

    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_collections')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_map')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_digital_heritage_browse')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('language_with_fallback', $this->config('views.view.mukurtu_browse_by_map')->get('display.default.display_options.filters'));
  }

}
