<?php

namespace Drupal\message_notify_ui\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Message notify ui sender settings form item annotation object.
 *
 * @see \Drupal\message_notify_ui\MessageNotifyUiSenderSettingsFormManager
 * @see plugin_api
 *
 * @Annotation
 */
class MessageNotifyUiSenderSettingsForm extends Plugin {

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
   * The plugin type the plugin take care off.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $notifyPlugin;

}
