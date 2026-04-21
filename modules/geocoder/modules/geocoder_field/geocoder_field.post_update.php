<?php

/**
 * @file
 * Post update functions for Geocoder Field.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;

/**
 * Rename the 'plugins' third party setting to 'providers'.
 */
function geocoder_field_post_update_rename_providers_in_third_party_settings(): void {
  /** @var \Drupal\geocoder_field\GeocoderFieldPluginManager $geocoder_field_plugin_manager */
  $geocoder_field_plugin_manager = \Drupal::service('geocoder_field.plugin.manager.field');
  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');

  // Compile a list of all field definitions that correspond to Geocoder field
  // types.
  $field_ids = [];
  foreach ($geocoder_field_plugin_manager->getFieldTypes() as $field_type) {
    foreach ($entity_field_manager->getFieldMapByFieldType($field_type) as $entity_type_id => $field_map) {
      foreach ($field_map as $field_name => $field_properties) {
        foreach ($field_properties['bundles'] as $bundle_id) {
          $field_ids[] = "$entity_type_id.$bundle_id.$field_name";
        }
      }
    }
  }

  // Iterate over the fields and rename the 'plugins' third party setting to
  // 'providers'.
  /** @var \Drupal\field\FieldConfigInterface $field_definition */
  foreach (FieldConfig::loadMultiple($field_ids) as $field_definition) {
    if (in_array('geocoder_field', $field_definition->getThirdPartyProviders())) {
      // Rename the 'plugins' third party setting to 'providers'.
      $settings = $field_definition->getThirdPartySetting('geocoder_field', 'plugins', []);
      $field_definition->setThirdPartySetting('geocoder_field', 'providers', $settings);
      $field_definition->unsetThirdPartySetting('geocoder_field', 'plugins');
      $field_definition->save();
    }
  }

}
