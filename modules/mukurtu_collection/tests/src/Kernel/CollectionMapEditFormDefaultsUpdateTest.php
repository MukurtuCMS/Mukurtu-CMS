<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_collection_update_40018(), which moves the collection edit
 * form map's default center off Null Island and sets a dedicated zoom for
 * editing a single already-saved point (#1453).
 *
 * @see mukurtu_collection_update_40018()
 * @group mukurtu_collection
 */
class CollectionMapEditFormDefaultsUpdateTest extends KernelTestBase {

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

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_collection');
    require_once $module_path . '/mukurtu_collection.install';
  }

  /**
   * The hook sets the center, empty-map zoom, and single-point zoom.
   */
  public function testUpdateSetsMapPositionDefaults(): void {
    \Drupal::configFactory()->getEditable('core.entity_form_display.node.collection.default')
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

    mukurtu_collection_update_40018();

    $settings = \Drupal::config('core.entity_form_display.node.collection.default')
      ->get('content.field_coverage.settings.map.map_position');
    $this->assertSame(2, $settings['zoom']);
    $this->assertSame(20, $settings['center']['lat']);
    $this->assertSame(10, $settings['center']['lon']);
    $this->assertSame(12, $settings['singlePointZoom']);
  }

  /**
   * The update hook is a no-op when the form display doesn't exist.
   */
  public function testUpdateIsNoOpWithoutFormDisplay(): void {
    $this->assertTrue(\Drupal::config('core.entity_form_display.node.collection.default')->isNew());
    mukurtu_collection_update_40018();
    $this->assertTrue(\Drupal::config('core.entity_form_display.node.collection.default')->isNew());
  }

}
