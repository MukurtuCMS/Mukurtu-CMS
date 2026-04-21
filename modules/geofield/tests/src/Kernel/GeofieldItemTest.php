<?php

namespace Drupal\Tests\geofield\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests using entity fields of the geofield field type.
 *
 * @group geofield
 */
class GeofieldItemTest extends FieldKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['geofield'];

  /**
   * Field storage entity.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * Field entity.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
  }

  /**
   * Tests processed properties.
   */
  public function testCrudAndUpdate() {
    $entity_type = 'entity_test';
    $this->createField($entity_type);

    // Create an entity with a random geofield field.
    $entity = EntityTest::create();
    $entity->geofield_field->value = $value = \Drupal::service('geofield.wkt_generator')->WktGenerateGeometry();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->geofield_field, 'Field implements interface.');
    $this->assertInstanceOf(FieldItemInterface::class, $entity->geofield_field[0], 'Field item implements interface.');
    $this->assertEquals($entity->geofield_field->value, $value);

    // Test computed values.
    $geom = \Drupal::service('geofield.geophp')->load($value);
    if (!empty($geom)) {
      $centroid = $geom->getCentroid();
      $bounding = $geom->getBBox();
      $computed = [];

      $computed['geo_type'] = $geom->geometryType();
      $computed['lon'] = $centroid->getX();
      $computed['lat'] = $centroid->getY();
      $computed['left'] = $bounding['minx'];
      $computed['top'] = $bounding['maxy'];
      $computed['right'] = $bounding['maxx'];
      $computed['bottom'] = $bounding['miny'];
      $computed['geohash'] = $geom->out('geohash');

      foreach ($computed as $index => $computed_value) {
        $this->assertEquals($entity->geofield_field->{$index}, $computed_value);
      }
    }

    // Test the generateSampleValue() method.
    $entity = EntityTest::create();
    $entity->geofield_field->generateSampleItems();
    $this->entityValidateAndSave($entity);
  }

  /**
   * Creates a geofield field storage and field.
   *
   * @param string $entity_type
   *   Entity type for which the field should be created.
   */
  protected function createField($entity_type) {
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'geofield_field',
      'entity_type' => $entity_type,
      'type' => 'geofield',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $entity_type,
      'description' => 'Description for geofield_field',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
      'required' => TRUE,
    ]);
    $this->field->save();
  }

}
