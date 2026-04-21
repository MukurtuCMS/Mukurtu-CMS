<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\MapCenterInterface;
use Drupal\geolocation\ViewsContextTrait;

/**
 * Derive center from boundary filter.
 *
 * @MapCenter(
 *   id = "views_boundary_argument",
 *   name = @Translation("Boundary argument - boundaries"),
 *   description = @Translation("Fit map to boundary argument."),
 * )
 */
class ViewsBoundaryArgument extends MapCenterBase implements MapCenterInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public function getAvailableMapCenterOptions($context) {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\HandlerBase $context */
      /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
      foreach ($displayHandler->getHandlers('argument') as $argument_id => $argument) {
        if ($argument->getPluginId() == 'geolocation_argument_boundary') {
          $options['boundary_argument_' . $argument_id] = $this->t('Boundary argument') . ' - ' . $argument->adminLabel();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $map, $center_option_id, array $center_option_settings, $context = NULL) {
    $map = parent::alterMap($map, $center_option_id, $center_option_settings, $context);

    if (!($displayHandler = self::getViewsDisplayHandler($context))) {
      return $map;
    }

    /** @var \Drupal\geolocation\Plugin\views\argument\BoundaryArgument $argument */
    $argument = $displayHandler->getHandler('argument', substr($center_option_id, 18));
    if ($values = $argument->getParsedBoundary()) {

      if (
        isset($values['lat_north_east'])
        && $values['lat_north_east'] !== ""
        && isset($values['lng_north_east'])
        && $values['lng_north_east'] !== ""
        && isset($values['lat_south_west'])
        && $values['lat_south_west'] !== ""
        && isset($values['lng_south_west'])
        && $values['lng_south_west'] !== ""
      ) {
        $map['#attached'] = BubbleableMetadata::mergeAttachments($map['#attached'], [
          'library' => [
            'geolocation/map_center.viewsBoundaryArgument',
          ],
          'drupalSettings' => [
            'geolocation' => [
              'maps' => [
                $map['#id'] => [
                  'map_center' => [
                    'views_boundary_argument' => [
                      'latNorthEast' => (float) $values['lat_north_east'],
                      'lngNorthEast' => (float) $values['lng_north_east'],
                      'latSouthWest' => (float) $values['lat_south_west'],
                      'lngSouthWest' => (float) $values['lng_south_west'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ]);
      }
    }

    return $map;
  }

}
