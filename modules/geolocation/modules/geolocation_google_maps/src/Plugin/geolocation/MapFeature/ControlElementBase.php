<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation_google_maps\Plugin\geolocation\MapProvider\GoogleMaps;

/**
 * Class ControlMapFeatureBase.
 */
abstract class ControlElementBase extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'position' => 'TOP_LEFT',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => GoogleMaps::getControlPositions(),
      '#default_value' => $settings['position'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents) {
    if (!in_array($values['position'], array_keys(GoogleMaps::getControlPositions()))) {
      $form_state->setErrorByName(implode('][', $parents), $this->t('No valid position.'));
    }
  }

}
