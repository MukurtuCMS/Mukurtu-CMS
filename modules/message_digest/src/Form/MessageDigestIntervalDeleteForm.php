<?php

namespace Drupal\message_digest\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Digest interval delete form.
 */
class MessageDigestIntervalDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.message_digest_interval.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete interval');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Delete %interval interval? This action cannot be undone.', ['%interval' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %label interval?', ['%label' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $placeholder = [
      '%label' => $this->entity->label(),
    ];
    $this->entity->delete();
    $this->logger('message_digest')->notice('The %label message digest interval has been deleted.', $placeholder);
    $this->messenger()->addMessage($this->t('The %label message digest interval has been deleted.', $placeholder));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
