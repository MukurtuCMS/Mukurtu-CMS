<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;

/**
 * Set protocols.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_migrate_set_protocols",
 *   handle_multiples = TRUE
 * )
 */
class SetProtocols extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value || empty($value) || !is_array($value)) {
      return "";
    }

    // Hack city. Rather than trying to figure out every different array format
    // on the migrate config side, find the array key being used here (e.g.,
    // target_id) and flatten the array for the protocols field. This is gross
    // but 100% fine for migrating from v3 -> v4.
    $protocol_ids = $value;
    $first = reset($value);
    if (is_array($first)) {
      $keys = array_keys($first);
      $key = reset($keys);
      $protocol_ids = array_column($value, $key);
    }

    return CulturalProtocolItem::formatProtocols($protocol_ids);
  }

}
