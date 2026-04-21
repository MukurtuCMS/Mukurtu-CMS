<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\ViewsContextTrait;

/**
 * Derive center from first row.
 *
 * @Location(
 *   id = "first_row",
 *   name = @Translation("View first row"),
 *   description = @Translation("Use geolocation field value from first row."),
 * )
 */
class FirstRow extends LocationBase implements LocationInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions($context) {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      if ($displayHandler->getPlugin('style')->getPluginId() == 'maps_common') {
        $options['first_row'] = $this->t('First row');
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
    $views_style = $displayHandler->getPlugin('style');

    if (empty($views_style->options['geolocation_field'])) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    /** @var \Drupal\geolocation\Plugin\views\field\GeolocationField $source_field */
    $source_field = $views_style->view->field[$views_style->options['geolocation_field']];

    if (empty($source_field)) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    if (empty($views_style->view->result[0])) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    /** @var \Drupal\geolocation\DataProviderInterface $data_provider */
    $data_provider = \Drupal::service('plugin.manager.geolocation.dataprovider')->getDataProviderByViewsField($source_field);

    $positions = $data_provider->getPositionsFromViewsRow($views_style->view->result[0], $source_field);

    if (!empty($positions[0])) {
      return $positions[0];
    }

    return parent::getCoordinates($location_option_id, $location_option_settings, $context);
  }

}
