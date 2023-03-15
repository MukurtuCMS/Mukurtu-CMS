<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;

/**
 * @MigrateProcessPlugin(
 *   id = "protocols",
 *   handle_multiples = TRUE
 * )
 */
class Protocols extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return CulturalProtocolItem::formatProtocols($value);
  }

}
