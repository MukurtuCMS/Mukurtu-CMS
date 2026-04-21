<?php

namespace Drupal\redirect\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\redirect\Entity\Redirect;

/**
 * The redirect delete confirmation form.
 */
class RedirectDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    assert($this->entity instanceof Redirect);
    return $this->t('Are you sure you want to delete the URL redirect from %source to %redirect?', ['%source' => $this->entity->getSourceUrl(), '%redirect' => $this->entity->getRedirectUrl()->toString()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('redirect.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    assert($this->entity instanceof Redirect);
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('The redirect %redirect has been deleted.', ['%redirect' => $this->entity->getRedirectUrl()->toString()]));
    $form_state->setRedirect('redirect.list');
  }

}
