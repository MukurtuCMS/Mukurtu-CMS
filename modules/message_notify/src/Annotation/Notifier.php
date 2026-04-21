<?php

namespace Drupal\message_notify\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a notifier plugin.
 *
 * @Annotation
 */
class Notifier extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable title.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Available view modes.
   *
   * @var array
   */
  public $viewModes;

}
