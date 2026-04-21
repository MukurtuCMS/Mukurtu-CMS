<?php

namespace Drupal\entity_browser\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an entity browser display annotation object.
 *
 * @see hook_entity_browser_display_info_alter()
 *
 * @Annotation
 */
class EntityBrowserDisplay extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the display.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the display.
   *
   * This will be shown when adding or configuring this display.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * Indicates that display uses route.
   *
   * @var string
   */
  public $uses_route = FALSE;

}
