<?php

namespace Drupal\message_notify_ui;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message\Entity\Message;

/**
 * Base class for Message notify ui sender settings form plugins.
 */
abstract class MessageNotifyUiSenderSettingsFormBase extends PluginBase implements MessageNotifyUiSenderSettingsFormInterface {
  use StringTranslationTrait;

  /**
   * The form settings.
   *
   * @var array
   */
  protected $form;

  /**
   * The form state interface.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * The message object.
   *
   * @var \Drupal\message\Entity\Message
   */
  protected $message;

  /**
   * Setter for the form variable.
   *
   * @param array $form
   *   The form API.
   *
   * @return $this
   */
  public function setForm(array $form) {
    $this->form = $form;

    return $this;
  }

  /**
   * Get the form API element.
   *
   * @return array
   *   The form API variable.
   */
  public function getForm() {
    return $this->form;
  }

  /**
   * Return the form state object.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state object.
   */
  public function getFormState() {
    return $this->formState;
  }

  /**
   * Set the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   *
   * @return $this
   */
  public function setFormState(FormStateInterface $formState) {
    $this->formState = $formState;
    return $this;
  }

  /**
   * Get the message object.
   *
   * @return \Drupal\message\Entity\Message
   *   The message object.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Set the message object.
   *
   * @param \Drupal\message\Entity\Message $message
   *   The message object.
   *
   * @return $this
   *   The current object.
   */
  public function setMessage(Message $message) {
    $this->message = $message;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $formState) {
    // Usually, there is nothing to validate.
  }

}
