<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Plugin\Field\FieldWidget\GeofieldMukurtuWidget;

/**
 * Tests the Map Points widget's dedicated single-point zoom setting (#1453).
 *
 * On the edit form, a saved single point should zoom in to a usable level
 * instead of falling back to the empty-map default zoom (which is
 * deliberately zoomed out to avoid world-map tiling on add forms). See
 * modules/mukurtu_core/js/mukurtu-leaflet-widget.js for the client-side
 * half of this fix.
 *
 * @group mukurtu_core
 */
class GeofieldMukurtuWidgetSinglePointZoomTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'field', 'geofield', 'leaflet', 'mukurtu_core'];

  /**
   * The default single point zoom must be distinct from the empty-map zoom.
   */
  public function testDefaultSettingsIncludeSinglePointZoom(): void {
    $defaults = GeofieldMukurtuWidget::defaultSettings();

    $this->assertArrayHasKey('singlePointZoom', $defaults['map']['map_position']);
    $this->assertSame(12, $defaults['map']['map_position']['singlePointZoom']);
    $this->assertNotEquals(
      $defaults['map']['map_position']['zoom'],
      $defaults['map']['map_position']['singlePointZoom'],
      'The single-point zoom must differ from the empty-map default zoom, otherwise the two settings serve no distinct purpose.'
    );
  }

}
