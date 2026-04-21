<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\MapCenterInterface;

/**
 * Fixed coordinates map center.
 *
 * @MapCenter(
 *   id = "client_location",
 *   name = @Translation("Client location"),
 *   description = @Translation("Automatically fit map to client location. Might not be available."),
 * )
 */
class ClientLocation extends MapCenterBase implements MapCenterInterface {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $map, $center_option_id, array $center_option_settings, $context = NULL) {
    $map = parent::alterMap($map, $center_option_id, $center_option_settings, $context);
    $map['#attached'] = BubbleableMetadata::mergeAttachments($map['#attached'], [
      'library' => [
        'geolocation/map_center.client_location',
      ],
    ]);

    return $map;
  }

}
