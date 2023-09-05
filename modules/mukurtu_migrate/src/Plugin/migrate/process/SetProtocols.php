<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

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
    if (!$value || empty($value)) {
      return "";
    }
    $protocol_ids = is_array($value) ? $value : [$value];
    // @todo This should really be calling something provided by
    // CulturalProtocolControlledTrait rather than embedding the logic directly.
    return implode(',', array_map(fn($p) => "|$p|", array_map('trim', $protocol_ids)));
  }

}
