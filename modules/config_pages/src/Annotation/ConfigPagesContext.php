<?php

namespace Drupal\config_pages\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a config page context item annotation object.
 *
 * Plugin Namespace: Plugin\config_pages\context.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class ConfigPagesContext extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the context.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
