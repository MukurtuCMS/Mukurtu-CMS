<?php

namespace Drupal\message\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Message arguments process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_message_arguments"
 * )
 */
class MessageProcessArguments extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Transform the arguments of the message.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = unserialize($value[0]);

    $arguments = [];
    foreach ($value as $key => $value) {
      $key = $key[0] == '!' ? substr_replace($key, '@', 0, 1) : $key;
      $arguments[$key] = $value;
    }

    return $arguments;
  }

}
