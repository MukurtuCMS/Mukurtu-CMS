<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "markdown_link"
 * )
 */
class MarkdownLink extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    preg_match('/\[(.*?)\]\s*?\\((.*)\)/', $value, $matches, PREG_UNMATCHED_AS_NULL);
    if ($matches[1] && $matches[2]) {
      return ['title' => $matches[1], 'uri' => $matches[2]];
    }
    return $value;
  }

}
