<?php

namespace Drupal\dashboards\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Dashboard item annotation object.
 *
 * @see \Drupal\dashboards\Plugin\DashboardManager
 * @see plugin_api
 *
 * @Annotation
 */
class Dashboard extends Plugin {

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
   * Category of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $category;

}
