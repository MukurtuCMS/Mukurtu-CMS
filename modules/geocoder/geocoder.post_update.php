<?php

/**
 * @file
 * Post update functions for Geocoder.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityStorageException;
use Drupal\geocoder\Entity\GeocoderProvider;

/**
 * Convert simple provider configuration to provider entities.
 */
function geocoder_post_update_convert_simple_config_to_entities(): void {
  // Ensure the new GeocoderProvider entity type is available.
  \Drupal::entityTypeManager()->clearCachedDefinitions();

  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('geocoder.settings');
  $plugin_definitions = \Drupal::service('plugin.manager.geocoder.provider')->getDefinitions();
  foreach ($config->get('plugins_options') as $plugin_id => $configuration) {
    if (!isset($plugin_definitions[$plugin_id])) {
      continue;
    }

    // Change key case to match the new version.
    $configuration['apiKey'] = $configuration['apikey'];
    unset($configuration['apikey']);

    try {
      GeocoderProvider::create([
        'id' => $plugin_id,
        'label' => $plugin_definitions[$plugin_id]['name'],
        'plugin' => $plugin_id,
        'configuration' => $configuration,
      ])->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::service('logger.channel.geocoder')->error($e->getMessage());
    }
  }
  $config->clear('plugins_options');
  $config->save();
}
