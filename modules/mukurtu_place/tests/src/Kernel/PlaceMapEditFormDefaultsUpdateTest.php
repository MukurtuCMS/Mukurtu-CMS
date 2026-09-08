<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_place\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_place_update_40009(), which moves the place edit form map's
 * default center off Null Island and sets a dedicated zoom for editing a
 * single already-saved point (#1453).
 *
 * @see mukurtu_place_update_40009()
 */
#[Group('mukurtu_place')]
class PlaceMapEditFormDefaultsUpdateTest extends KernelTestBase {

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

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_place');
    require_once $module_path . '/mukurtu_place.install';
  }

  /**
   * The hook sets the center, empty-map zoom, and single-point zoom.
   */
  public function testUpdateSetsMapPositionDefaults(): void {
    \Drupal::configFactory()->getEditable('core.entity_form_display.node.place.default')
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

    mukurtu_place_update_40009();

    $settings = \Drupal::config('core.entity_form_display.node.place.default')
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
    $this->assertTrue(\Drupal::config('core.entity_form_display.node.place.default')->isNew());
    mukurtu_place_update_40009();
    $this->assertTrue(\Drupal::config('core.entity_form_display.node.place.default')->isNew());
  }

}
