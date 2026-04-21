<?php

namespace Drupal\message_ui\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Message UI views contextual links item annotation object.
 *
 * @see \Drupal\message_ui\MessageUiViewsContextualLinksManager
 * @see plugin_api
 *
 * @Annotation
 */
class MessageUiViewsContextualLinks extends Plugin {

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

}
