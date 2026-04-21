<?php

namespace Drupal\layout_builder_restrictions\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Layout builder restriction plugin item annotation object.
 *
 * @see \Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager
 * @see plugin_api
 *
 * @Annotation
 */
class LayoutBuilderRestriction extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A description of the plugin (optional).
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = NULL;

}
