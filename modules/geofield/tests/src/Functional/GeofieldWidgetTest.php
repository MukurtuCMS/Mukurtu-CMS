<?php

namespace Drupal\Tests\geofield\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Functional\FieldTestBase;

/**
 * Tests the Geofield widgets.
 *
 * @group geofield
 */
class GeofieldWidgetTest extends FieldTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['geofield', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A field storage with cardinality 1 to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * A Field to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * The web assert object.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $assertSession;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'geofield_field',
      'entity_type' => 'entity_test',
      'type' => 'geofield',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'description' => 'Description for geofield_field',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
      'required' => TRUE,
    ]);
    $this->field->save();

    $this->assertSession = $this->assertSession();

    // Create a web user.
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]));
  }

  /**
   * Tests the Default widget.
   */
  public function testDefaultWidget() {
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($this->fieldStorage->getName(), [
        'type' => 'geofield_default',
      ])
      ->save();

    // Create an entity.
    $entity = EntityTest::create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    // With no field data, no buttons are checked.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->assertSession->pageTextContains('geofield_field');

    // Test a valid WKT value.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value]' => 'POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))']);

    // Test a valid GeoJSON value.
    $edit = [
      'name[0][value]' => 'Dinagat Islands',
      'geofield_field[0][value]' => '{
  "type": "Feature",
  "geometry": {
    "type": "Point",
    "coordinates": [125.6, 10.1]
  },
  "properties": {
    "name": "Dinagat Islands"
  }
}',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (125.6 10.1)']);

    // Test a valid WKB value.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value]' => '0101000020E6100000705F07CE19D100C0865AD3BCE31C4540',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (-2.1021 42.2257)']);
  }

  /**
   * Tests the Lat Lon widget.
   */
  public function testLatLonWidget() {
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($this->fieldStorage->getName(), [
        'type' => 'geofield_latlon',
      ])
      ->save();

    // Create an entity.
    $entity = EntityTest::create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    // Check basic data.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->assertSession->pageTextContains('geofield_field');
    $this->assertSession->pageTextContains('Latitude');
    $this->assertSession->pageTextContains('Longitude');

    // Add a valid point.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value][lat]' => 42.2257,
      'geofield_field[0][value][lon]' => -2.1021,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (-2.1021 42.2257)']);

    // Add a valid point with lat 0.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value][lat]' => 0,
      'geofield_field[0][value][lon]' => -2.1021,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (-2.1021 0)']);

    // Add a valid point with lon 0.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value][lat]' => 42.2257,
      'geofield_field[0][value][lon]' => 0,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (0 42.2257)']);

    // Add values out of range.
    $edit = [
      'name[0][value]' => 'Out of bounds',
      'geofield_field[0][value][lat]' => 92.2257,
      'geofield_field[0][value][lon]' => -200.1021,
    ];
    $this->submitForm($edit, 'Save');

    $this->assertSession->pageTextContains('geofield_field: Latitude is out of bounds');
    $this->assertSession->pageTextContains('geofield_field: Longitude is out of bounds');

    // Add non numeric values.
    $edit = [
      'name[0][value]' => 'Not numeric',
      'geofield_field[0][value][lat]' => 'Not',
      'geofield_field[0][value][lon]' => 'Numeric',
    ];
    $this->submitForm($edit, 'Save');

    $this->assertSession->pageTextContains('geofield_field: Latitude is not valid.');
    $this->assertSession->pageTextContains('geofield_field: Longitude is not valid.');
  }

  /**
   * Tests the bounds widget.
   */
  public function testBoundsWidget() {
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($this->fieldStorage->getName(), [
        'type' => 'geofield_bounds',
      ])
      ->save();

    // Create an entity.
    $entity = EntityTest::create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    // Check basic data.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->assertSession->statusCodeEquals(200);
    $this->assertSession->pageTextContains('geofield_field');
    $this->assertSession->pageTextContains('Top');
    $this->assertSession->pageTextContains('Right');
    $this->assertSession->pageTextContains('Bottom');
    $this->assertSession->pageTextContains('Left');

    // Add valid bounds.
    $edit = [
      'name[0][value]' => 'Arnedo - Valladolid',
      'geofield_field[0][value][top]' => 42.2257,
      'geofield_field[0][value][right]' => -2.1021,
      'geofield_field[0][value][bottom]' => 41.6523,
      'geofield_field[0][value][left]' => -4.7245,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POLYGON ((-2.1021 42.2257, -2.1021 41.6523, -4.7245 41.6523, -4.7245 42.2257, -2.1021 42.2257))']);

    // Add invalid bounds.
    $edit = [
      'name[0][value]' => 'Invalid',
      'geofield_field[0][value][top]' => 42.2257,
      'geofield_field[0][value][right]' => 'non numeric',
      'geofield_field[0][value][bottom]' => 45.2257,
      'geofield_field[0][value][left]' => 750,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession->pageTextContains('geofield_field: Right is not valid.');
    $this->assertSession->pageTextContains('geofield_field: Left is out of bounds');
    $this->assertSession->pageTextContains('geofield_field: Top must be greater than Bottom.');
  }

  /**
   * Tests the DMS widget.
   */
  public function testDmsWidget() {
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($this->fieldStorage->getName(), [
        'type' => 'geofield_dms',
      ])
      ->save();

    // Create an entity.
    $entity = EntityTest::create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    // Check basic data.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->assertSession->pageTextContains('geofield_field');

    // Add valid data.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value][lat][orientation]' => 'N',
      'geofield_field[0][value][lat][degrees]' => 42,
      'geofield_field[0][value][lat][minutes]' => 13,
      'geofield_field[0][value][lat][seconds]' => 32,
      'geofield_field[0][value][lon][orientation]' => 'W',
      'geofield_field[0][value][lon][degrees]' => 2,
      'geofield_field[0][value][lon][minutes]' => 6,
      'geofield_field[0][value][lon][seconds]' => 7,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity, 'geofield_field', ['POINT (-2.1019444444 42.2255555556)']);

    // Add invalid data.
    $edit = [
      'name[0][value]' => 'Arnedo',
      'geofield_field[0][value][lat][orientation]' => 'N',
      'geofield_field[0][value][lat][degrees]' => 72,
      'geofield_field[0][value][lat][minutes]' => 555,
      'geofield_field[0][value][lat][seconds]' => 32.5,
      'geofield_field[0][value][lon][orientation]' => 'W',
      'geofield_field[0][value][lon][degrees]' => 2,
      'geofield_field[0][value][lon][minutes]' => 'non numeric',
      'geofield_field[0][value][lon][seconds]' => 7,
    ];
    $this->submitForm($edit, 'Save');

    $this->assertSession->pageTextContains('geofield_field must be lower than or equal to 59.');
    $this->assertSession->pageTextContains('geofield_field is not a valid number.');
    $this->assertSession->pageTextContains('geofield_field must be a number.');

  }

}
