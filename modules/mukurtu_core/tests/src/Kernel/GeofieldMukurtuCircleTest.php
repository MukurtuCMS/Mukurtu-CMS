<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests circle support in the Mukurtu Leaflet geofield widget.
 *
 * GeoJSON has no circle geometry type, so the widget's JS (see
 * mukurtu-leaflet-widget.js) stores a drawn circle as a polygon
 * approximation carrying custom circle_center/circle_radius Feature
 * properties. That design only works if arbitrary Feature properties
 * survive entity save/reload untouched, and if the widget's toolbar
 * setting is actually enable-able (contrib force-disables it).
 *
 * @see \Drupal\mukurtu_core\Plugin\Field\FieldWidget\GeofieldMukurtuWidget
 */
#[Group('mukurtu_core')]
class GeofieldMukurtuCircleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity_test',
    'geofield',
    'leaflet',
    // mukurtu_core_entity_type_build() unconditionally alters the 'media'
    // entity type, and mukurtu_core_entity_extra_field_info() unconditionally
    // loads node types, so both modules must be enabled for mukurtu_core to
    // install cleanly regardless of what entity type this test targets.
    'file',
    'image',
    'media',
    'text',
    'node',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_circle_test',
      'entity_type' => 'entity_test',
      'type' => 'geofield',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_circle_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Custom circle_center/circle_radius Feature properties survive save/reload.
   *
   * This is the load-bearing assumption behind storing circles as
   * polygon approximations: nothing in the field type or storage layer
   * may strip or normalize the GeoJSON on the way to/from the database.
   */
  public function testCirclePropertiesSurviveSaveAndReload(): void {
    $geojson = json_encode([
      'type' => 'FeatureCollection',
      'features' => [
        [
          'type' => 'Feature',
          'properties' => [
            'circle_center' => [46.7, -117.2],
            'circle_radius' => 500,
            'location_description' => 'Test circle',
          ],
          'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[[-117.2, 46.7], [-117.19, 46.7], [-117.2, 46.71], [-117.2, 46.7]]],
          ],
        ],
      ],
    ]);

    $entity = EntityTest::create(['name' => 'Circle test']);
    $entity->set('field_circle_test', $geojson);
    $entity->save();

    $storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $storage->resetCache([$entity->id()]);
    $reloaded = $storage->load($entity->id());

    $this->assertSame($geojson, $reloaded->get('field_circle_test')->value, 'Stored GeoJSON must round-trip byte-for-byte so circle_center/circle_radius are not lost.');

    $stored = json_decode($reloaded->get('field_circle_test')->value, TRUE);
    $this->assertSame([46.7, -117.2], $stored['features'][0]['properties']['circle_center']);
    $this->assertSame(500, $stored['features'][0]['properties']['circle_radius']);
  }

  /**
   * The widget's settingsForm() enables the drawCircle toolbar checkbox.
   *
   * Contrib's LeafletDefaultWidget::settingsForm() hardcodes
   * '#disabled' => TRUE on this checkbox with the label "(unsupported by
   * GeoJSON)". GeofieldMukurtuWidget overrides settingsForm() to enable
   * it, since Mukurtu's JS converts circles to polygons before they
   * ever reach GeoJSON serialization.
   */
  public function testSettingsFormEnablesDrawCircle(): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_circle_test', ['type' => 'geofield_mukurtu']);
    $display->save();

    $widget = $display->getRenderer('field_circle_test');
    // Field UI's entity display edit form normally supplies these; without
    // them, contrib's setMapGeoJsonOverlays() emits an undefined-key notice.
    $form = ['#entity_type' => 'entity_test', '#bundle' => 'entity_test'];
    $form_state = new FormState();
    $settings_form = $widget->settingsForm($form, $form_state);

    $this->assertArrayHasKey('drawCircle', $settings_form['toolbar']);
    $this->assertFalse($settings_form['toolbar']['drawCircle']['#disabled'], 'The drawCircle checkbox must not be disabled by the Mukurtu widget.');
    $this->assertSame('Adds button to draw circle.', (string) $settings_form['toolbar']['drawCircle']['#title']);
  }

}
