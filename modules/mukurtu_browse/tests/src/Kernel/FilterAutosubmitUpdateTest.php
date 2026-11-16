<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that the browse filters wait for Apply (#2619).
 *
 * Covers the shipped views and mukurtu_browse_update_40024() and
 * mukurtu_dictionary_update_40049(), which turn off Better Exposed Filters
 * auto-submit on existing sites.
 */
#[Group('mukurtu_browse')]
class FilterAutosubmitUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Views and Better Exposed Filters supply the schema for the saved views.
   * mukurtu_browse lets the dictionary update load its install file.
   */
  protected static $modules = [
    'better_exposed_filters',
    'mukurtu_browse',
    'views',
  ];

  /**
   * The key holding a view's exposed form settings.
   */
  protected const KEY = 'display.default.display_options.exposed_form';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $modules = \Drupal::service('extension.list.module');
    require_once $modules->getPath('mukurtu_browse') . '/mukurtu_browse.install';
    require_once $modules->getPath('mukurtu_dictionary') . '/mukurtu_dictionary.install';
  }

  /**
   * Shipped views, keyed by view ID, with the module that ships them.
   */
  public static function shippedViews(): array {
    return [
      'mukurtu_browse' => ['mukurtu_browse', 'mukurtu_browse'],
      'mukurtu_digital_heritage_browse' => ['mukurtu_browse', 'mukurtu_digital_heritage_browse'],
      'mukurtu_dictionary' => ['mukurtu_dictionary', 'mukurtu_dictionary'],
    ];
  }

  /**
   * New sites get views whose filters wait for Apply.
   */
  #[DataProvider('shippedViews')]
  public function testShippedViewsDoNotAutosubmit(string $module, string $view_id): void {
    $path = \Drupal::service('extension.list.module')->getPath($module) . "/config/install/views.view.$view_id.yml";
    $view = Yaml::parseFile($path);
    $exposed_form = $view['display']['default']['display_options']['exposed_form'];
    $this->assertSame('bef', $exposed_form['type']);
    $this->assertFalse($exposed_form['options']['bef']['general']['autosubmit']);
    $this->assertFalse($exposed_form['options']['bef']['general']['autosubmit_hide']);
  }

  /**
   * The updates turn off auto-submit and leave everything else alone.
   */
  public function testUpdatesTurnOffAutosubmit(): void {
    foreach (array_keys(static::shippedViews()) as $view_id) {
      $this->saveView($view_id);
    }
    // A site that swapped in another exposed form type keeps its settings.
    \Drupal::configFactory()->getEditable('views.view.other_view')
      ->set('id', 'other_view')
      ->set('display.default.display_plugin', 'default')
      ->set(static::KEY, ['type' => 'basic', 'options' => ['submit_button' => 'Go']])
      ->save();

    mukurtu_browse_update_40024();
    mukurtu_dictionary_update_40049();

    foreach (array_keys(static::shippedViews()) as $view_id) {
      $general = $this->config("views.view.$view_id")->get(static::KEY . '.options.bef.general');
      $this->assertFalse($general['autosubmit'], "$view_id no longer auto-submits.");
      $this->assertFalse($general['autosubmit_hide'], "$view_id shows Apply.");
      $this->assertSame(500, $general['autosubmit_textfield_delay'], "$view_id keeps its other settings.");
    }

    $this->assertSame(
      ['type' => 'basic', 'options' => ['submit_button' => 'Go']],
      $this->config('views.view.other_view')->get(static::KEY),
    );
  }

  /**
   * A site that deleted a view doesn't get it back.
   */
  public function testUpdatesSkipMissingViews(): void {
    mukurtu_browse_update_40024();
    mukurtu_dictionary_update_40049();

    foreach (array_keys(static::shippedViews()) as $view_id) {
      $this->assertTrue($this->config("views.view.$view_id")->isNew(), "$view_id isn't created.");
    }
  }

  /**
   * Saves the part of a view the updates read, with auto-submit on.
   */
  protected function saveView(string $view_id): void {
    \Drupal::configFactory()->getEditable("views.view.$view_id")
      ->set('id', $view_id)
      ->set('display.default.display_plugin', 'default')
      ->set(static::KEY, [
        'type' => 'bef',
        'options' => [
          'bef' => [
            'general' => [
              'autosubmit' => TRUE,
              'autosubmit_hide' => TRUE,
              'autosubmit_textfield_delay' => 500,
            ],
          ],
        ],
      ])
      ->save();
  }

}
