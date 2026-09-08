<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40041(), which moves the dictionary word
 * and word list edit form maps' default center off Null Island and sets a
 * dedicated zoom for editing a single already-saved point (#1453).
 *
 * @see mukurtu_dictionary_update_40041()
 */
#[Group('mukurtu_dictionary')]
class DictionaryMapEditFormDefaultsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The hook writes a partial form-display config fixture, not full data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary');
    require_once $module_path . '/mukurtu_dictionary.install';
  }

  /**
   * The hook sets the center, empty-map zoom, and single-point zoom for
   * both the dictionary_word and word_list edit forms.
   */
  public function testUpdateSetsMapPositionDefaultsOnBothBundles(): void {
    foreach (['dictionary_word', 'word_list'] as $bundle) {
      \Drupal::configFactory()->getEditable("core.entity_form_display.node.$bundle.default")
        ->setData([
          'content' => [
            'field_coverage' => [
              'settings' => [
                'map' => [
                  'map_position' => [
                    'zoom' => 0,
                    'center' => ['lat' => 0, 'lon' => 0],
                  ],
                ],
              ],
            ],
          ],
        ])
        ->save();
    }

    mukurtu_dictionary_update_40041();

    foreach (['dictionary_word', 'word_list'] as $bundle) {
      $settings = \Drupal::config("core.entity_form_display.node.$bundle.default")
        ->get('content.field_coverage.settings.map.map_position');
      $this->assertSame(2, $settings['zoom'], "zoom for $bundle");
      $this->assertSame(20, $settings['center']['lat'], "center.lat for $bundle");
      $this->assertSame(10, $settings['center']['lon'], "center.lon for $bundle");
      $this->assertSame(12, $settings['singlePointZoom'], "singlePointZoom for $bundle");
    }
  }

  /**
   * The update hook skips a bundle whose form display doesn't exist, and
   * still updates the other one.
   */
  public function testUpdateSkipsMissingFormDisplay(): void {
    \Drupal::configFactory()->getEditable('core.entity_form_display.node.word_list.default')
      ->setData([
        'content' => [
          'field_coverage' => [
            'settings' => [
              'map' => [
                'map_position' => [
                  'zoom' => 0,
                  'center' => ['lat' => 0, 'lon' => 0],
                ],
              ],
            ],
          ],
        ],
      ])
      ->save();

    $this->assertTrue(\Drupal::config('core.entity_form_display.node.dictionary_word.default')->isNew());

    mukurtu_dictionary_update_40041();

    $this->assertTrue(\Drupal::config('core.entity_form_display.node.dictionary_word.default')->isNew());
    $this->assertSame(
      12,
      \Drupal::config('core.entity_form_display.node.word_list.default')
        ->get('content.field_coverage.settings.map.map_position.singlePointZoom')
    );
  }

}
