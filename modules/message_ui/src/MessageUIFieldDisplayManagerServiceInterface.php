<?php

namespace Drupal\message_ui;

/**
 * Field Display Manager Service Interface.
 *
 * @package Drupal\message_ui
 */
interface MessageUIFieldDisplayManagerServiceInterface {

  /**
   * Setting the fields to display.
   *
   * @param string $template
   *   The message template.
   */
  public function setFieldsDisplay($template);

}
