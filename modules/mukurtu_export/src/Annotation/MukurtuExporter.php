<?php

namespace Drupal\mukurtu_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MukurtuExporter annotation object.
 *
 * @Annotation
 */
class MukurtuExporter extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;
}