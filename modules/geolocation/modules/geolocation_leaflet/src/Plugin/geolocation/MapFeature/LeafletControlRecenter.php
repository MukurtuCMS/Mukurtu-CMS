<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

/**
 * Provides Recenter control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_recenter",
 *   name = @Translation("Map Control - Recenter"),
 *   description = @Translation("Add button to recenter map."),
 *   type = "leaflet",
 * )
 */
class LeafletControlRecenter extends ControlCustomElementBase {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $settings, $context);

    $render_array['#controls'][$this->getPluginId()]['control_recenter'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#attributes' => [
        'class' => ['recenter'],
        'href' => '#',
        'title' => $this->t('Recenter'),
        'role' => 'button',
      ],
    ];
    $render_array['#controls'][$this->getPluginId()]['#attributes']['class'][] = 'leaflet-bar';

    return $render_array;
  }

}
