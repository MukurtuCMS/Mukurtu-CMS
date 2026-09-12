<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\CircleGeometry;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that circles are editable in the accessible lat/long widget (#2164).
 *
 * Two defects, one of which was silent:
 *
 * A circle is stored as a 128 sided polygon carrying circle_center and
 * circle_radius, so the accessible editor rendered 128 latitude/longitude
 * rows for it, labelled as an ordinary polygon.
 *
 * Worse, editing those rows achieved nothing. massageFormValues() rewrote the
 * geometry but carried the original properties through untouched, and the map
 * widget rebuilds the circle from circle_center and circle_radius, discarding
 * the edited geometry. A keyboard user's changes were accepted and then thrown
 * away. That is the round trip asserted below.
 *
 * @see \Drupal\mukurtu_core\CircleGeometry
 */
#[Group('mukurtu_core')]
class AccessibleCircleEditingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    NodeType::create(['type' => 'place', 'name' => 'Place'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'type' => 'geofield',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'bundle' => 'place',
      'label' => 'Coverage',
    ])->save();

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'place',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_coverage', [
      'type' => 'geofield_mukurtu_latlon',
      'region' => 'content',
    ])->save();
  }

  /**
   * A stored circle, exactly as the map widget writes one.
   */
  private function storedCircle(float $lat, float $lon, float $radius, string $description = ''): string {
    $feature = CircleGeometry::applyToFeature(['type' => 'Feature', 'properties' => []], $lat, $lon, $radius);
    if ($description !== '') {
      $feature['properties']['location_description'] = $description;
    }

    return json_encode(['type' => 'FeatureCollection', 'features' => [$feature]]);
  }

  /**
   * Builds the widget's form element for a stored value.
   *
   * Calls the widget directly rather than building the whole node form. The
   * node form drags in unrelated dependencies (its date field needs date
   * format config, for one) and none of that is what is under test here.
   */
  private function buildWidget(string $value): array {
    $node = Node::create(['type' => 'place', 'title' => 'Somewhere', 'field_coverage' => $value]);

    $form = [];
    $form_state = new FormState();
    $element = ['#field_parents' => [], '#delta' => 0];

    $built = $this->widget()->formElement(
      $node->get('field_coverage'),
      0,
      $element,
      $form,
      $form_state
    );

    return $built['value'];
  }

  /**
   * The lat/long widget plugin, configured for field_coverage.
   */
  private function widget() {
    return $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', 'place')['field_coverage'],
      'form_mode' => 'default',
      'configuration' => [
        'type' => 'geofield_mukurtu_latlon',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);
  }

  /**
   * The editor offers a centre and a radius, not 128 vertex rows.
   */
  public function testCircleRendersAsCentreAndRadius(): void {
    $widget = $this->buildWidget($this->storedCircle(45.0, -100.0, 2500.0));
    $shape = $widget['shapes'][0];

    $this->assertSame('Circle', $shape['shape_type']['#value']);
    $this->assertArrayHasKey('radius', $shape, 'The circle has no radius field.');
    $this->assertArrayNotHasKey('vertices', $shape, 'The circle is still rendered as a list of vertices.');

    // The centre is pre-filled from the stored metadata.
    $this->assertEquals(45.0, (float) $shape['coordinates']['#default_value']['lat']);
    $this->assertEquals(-100.0, (float) $shape['coordinates']['#default_value']['lon']);
    $this->assertEquals(2500.0, (float) $shape['radius']['#default_value']);
  }

  /**
   * An ordinary polygon is untouched by any of this.
   */
  public function testPlainPolygonStillRendersAsVertices(): void {
    $polygon = json_encode([
      'type' => 'FeatureCollection',
      'features' => [[
        'type' => 'Feature',
        'properties' => [],
        'geometry' => [
          'type' => 'Polygon',
          'coordinates' => [[[0, 0], [0, 1], [1, 1], [0, 0]]],
        ],
      ]],
    ]);

    $shape = $this->buildWidget($polygon)['shapes'][0];

    $this->assertSame('Polygon', $shape['shape_type']['#value']);
    $this->assertArrayHasKey('vertices', $shape);
    $this->assertArrayNotHasKey('radius', $shape);
  }

  /**
   * Editing the centre and radius actually persists.
   *
   * This is the bug. Before the fix the geometry moved but circle_center and
   * circle_radius did not, so the map rebuilt the original circle and the edit
   * vanished.
   */
  public function testEditingACirclePersists(): void {
    $original = json_decode($this->storedCircle(45.0, -100.0, 2500.0, 'Original place'), TRUE);
    $sourceFeature = $original['features'][0];

    $widget = $this->widget();

    $values = [[
      'value' => [
        'preserved_features' => [],
        'shapes' => [[
          'shape_type' => 'Circle',
          'coordinates' => ['lat' => '10.5', 'lon' => '20.25'],
          'radius' => '750',
          'location_description' => 'Original place',
          'source_feature' => $sourceFeature,
          'source_index' => 0,
        ]],
      ],
    ]];

    $massaged = $widget->massageFormValues($values, [], new FormState());
    $saved = json_decode($massaged[0]['value'], TRUE);
    $feature = $saved['features'][0];

    // The metadata the map rebuilds from must have moved with the edit.
    $this->assertEqualsWithDelta(10.5, (float) $feature['properties']['circle_center'][0], 0.0001, 'circle_center latitude was not updated, so the map would rebuild the original circle and discard this edit.');
    $this->assertEqualsWithDelta(20.25, (float) $feature['properties']['circle_center'][1], 0.0001, 'circle_center longitude was not updated.');
    $this->assertEqualsWithDelta(750.0, (float) $feature['properties']['circle_radius'], 0.0001, 'circle_radius was not updated.');

    // And the geometry must agree with it, rather than being left behind.
    $this->assertSame('Polygon', $feature['geometry']['type']);
    $ring = $feature['geometry']['coordinates'][0];
    $this->assertCount(CircleGeometry::SIDES + 1, $ring);

    $reread = CircleGeometry::fromFeature($feature);
    $this->assertEqualsWithDelta(10.5, $reread['lat'], 0.000001);
    $this->assertEqualsWithDelta(20.25, $reread['lon'], 0.000001);

    // Unrelated properties survive.
    $this->assertSame('Original place', $feature['properties']['location_description']);
  }

  /**
   * A circle with no radius is dropped rather than saved as a broken shape.
   */
  public function testCircleWithoutARadiusIsDropped(): void {
    $widget = $this->widget();

    $values = [[
      'value' => [
        'preserved_features' => [],
        'shapes' => [[
          'shape_type' => 'Circle',
          'coordinates' => ['lat' => '10', 'lon' => '20'],
          'radius' => '',
          'source_feature' => ['type' => 'Feature', 'properties' => []],
          'source_index' => NULL,
        ]],
      ],
    ]];

    $massaged = $widget->massageFormValues($values, [], new FormState());

    $this->assertSame('', $massaged[0]['value'], 'A circle with no radius should be dropped, not saved.');
  }

}
