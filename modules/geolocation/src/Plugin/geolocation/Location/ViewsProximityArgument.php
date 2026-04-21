<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\ViewsContextTrait;

/**
 * Derive center from proximity argument.
 *
 * @Location(
 *   id = "views_proximity_argument",
 *   name = @Translation("Proximity argument"),
 *   description = @Translation("Set map center from proximity argument."),
 * )
 */
class ViewsProximityArgument extends LocationBase implements LocationInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions($context) {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
      foreach ($displayHandler->getHandlers('argument') as $argument_id => $argument) {
        if ($argument->getPluginId() == 'geolocation_argument_proximity') {
          $options[$argument_id] = $this->t('Proximity argument') . ' - ' . $argument->adminLabel();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($location_option_id, array $location_option_settings, $context = NULL) {
    if (!($displayHandler = self::getViewsDisplayHandler($context))) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    /** @var \Drupal\geolocation\Plugin\views\argument\ProximityArgument $handler */
    $handler = $displayHandler->getHandler('argument', $location_option_id);
    if (empty($handler)) {
      return FALSE;
    }
    if ($values = $handler->getParsedReferenceLocation()) {
      if (isset($values['lat']) && isset($values['lng'])) {
        return [
          'lat' => $values['lat'],
          'lng' => $values['lng'],
        ];
      }
    }

    return parent::getCoordinates($location_option_id, $location_option_settings, $context);
  }

}
