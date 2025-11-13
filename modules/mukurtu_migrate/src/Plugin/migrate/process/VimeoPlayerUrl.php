<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transform a Vimeo video ID into a player URL.
 *
 * @MigrateProcessPlugin(
 *   id = "vimeo_player_url"
 * )
 */
class VimeoPlayerUrl extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return $value;
    }
    return 'https://player.vimeo.com/video/' . $value;
  }

}
