<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

/**
 * Provides Locate control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_locate",
 *   name = @Translation("Map Control - Locate"),
 *   description = @Translation("Add button to center on client location. Hidden on non-https connection."),
 *   type = "leaflet",
 * )
 */
class LeafletControlLocate extends ControlCustomElementBase {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#controls'][$this->getPluginId()]['control_locate'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#attributes' => [
        'class' => ['locate'],
        'href' => '#',
        'title' => $this->t('Locate'),
        'role' => 'button',
      ],
    ];
    $render_array['#controls'][$this->getPluginId()]['#attributes']['class'][] = 'leaflet-bar';

    return $render_array;
  }

}
