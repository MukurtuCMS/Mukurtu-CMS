<?php

namespace Drupal\message_ui\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a message_ui entity.
 *
 * @ingroup message_ui
 */
class MessageDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this message entity?');
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the message list.
   */
  public function getCancelUrl() {
    return new Url('message.messages');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $this->logger('message_ui')->notice('@type: deleted message entity.', ['@type' => $this->entity->bundle()]);
    $form_state->setRedirect('message.messages');
  }

}
