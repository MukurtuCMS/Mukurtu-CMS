<?php

namespace Drupal\entity_browser\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an entity browser widget validation annotation object.
 *
 * @see hook_entity_browser_widget_validation_info_alter()
 *
 * @Annotation
 */
class EntityBrowserWidgetValidation extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the widget validator.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The data type plugin ID, for which a constraint should be added (Optional).
   *
   * @var string
   */
  public $data_type;

  /**
   * The constraint ID (Optional).
   *
   * @var string
   */
  public $constraint;

}
