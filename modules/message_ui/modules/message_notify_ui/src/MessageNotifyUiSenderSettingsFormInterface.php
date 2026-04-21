<?php

namespace Drupal\message_notify_ui;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\message_notify\MessageNotifier;

/**
 * Defines an interface for Message notify ui sender settings form plugins.
 */
interface MessageNotifyUiSenderSettingsFormInterface extends PluginInspectionInterface {

  /**
   * The form settings for the plugin.
   *
   * @return array
   *   The form with the setting of the plugin.
   */
  public function form();

  /**
   * Validating the form.
   *
   * @param array $form
   *   The form API element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   */
  public function validate(array $form, FormStateInterface $formState);

  /**
   * Implementing logic for sender which relate to the plugin.
   *
   * Each plugin of this type provide UI for a notifier plugin. After the form
   * is submitted this function will be invoked.
   *
   * @param \Drupal\message_notify\MessageNotifier $notifier
   *   The notifier which the plugin take care.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   */
  public function submit(MessageNotifier $notifier, FormStateInterface $formState);

}
