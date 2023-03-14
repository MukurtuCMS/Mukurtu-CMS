<?php

namespace Drupal\mukurtu_import\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines mukurtu_import_field_process annotation object.
 *
 * @Annotation
 */
class MukurtuImportFieldProcess extends Plugin {

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
