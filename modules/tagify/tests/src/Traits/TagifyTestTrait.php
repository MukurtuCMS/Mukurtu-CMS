<?php

namespace Drupal\Tests\tagify\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test trait with helper functions.
 */
trait TagifyTestTrait {

  /**
   * Creates a new field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle that this field will be added to.
   * @param string $field_type
   *   The field type.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param string $widget_type
   *   The widget for the new field.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createField($name, $entity_type, $bundle, $field_type, array $storage_settings = [], array $field_settings = [], $widget_type = 'string', array $widget_settings = []) {
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $name,
      'type' => $field_type,
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ]);
    $field_storage->save();

    $field = [
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ];
    FieldConfig::create($field)->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository */
    $entity_display_repository = \Drupal::service('entity_display.repository');
    $entity_display_repository->getFormDisplay($entity_type, $bundle, 'default')
      ->setComponent($name, [
        'type' => $widget_type,
        'settings' => $widget_settings,
      ])
      ->save();
  }

}
