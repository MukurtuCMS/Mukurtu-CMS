<?php

namespace Drupal\message\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for message template deletion.
 */
class MessageTemplateDeleteConfirm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the message template %template?', [
      '%template' => $this->entity->label(),
    ]);
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check if any messages are using this template.
    $number_messages = $this->entityTypeManager->getStorage('message')->getQuery()
      // No access check since the messages are not displayed. If an access
      // check were made, a template might accidentally be deleted while
      // messages were still using it.
      ->accessCheck(FALSE)
      ->condition('template', $this->entity->id())
      ->count()
      ->execute();
    if ($number_messages) {
      $caption = '<p>' . $this->formatPlural($number_messages, '%template is used by 1 message on your site. You cannot remove this message template until you have removed all of the %template messages.', '%template is used by @count messages on your site. You may not remove %template until you have removed all of the %template messages.', ['%template' => $this->entity->label()]) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $t_args = ['%name' => $this->entity->label()];
    $this->messenger()->addMessage($this->t('The message template %name has been deleted.', $t_args));
    $this->logger('content')->notice('Deleted message template %name', $t_args);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Returns the route to go to if the user cancels the action.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public function getCancelUrl() {
    return new Url('message.overview_templates');
  }

}
