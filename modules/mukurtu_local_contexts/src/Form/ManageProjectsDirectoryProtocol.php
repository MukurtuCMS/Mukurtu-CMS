<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to manage the protocol level local contexts projects directory.
 */
class ManageProjectsDirectoryProtocol extends ManageProjectsDirectoryBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_protocol_projects_directory';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $group = NULL) {
    $form_state->setTemporaryValue('group', $group);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No operation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->getTemporaryValue('group');
    $this->messenger()->addMessage($this->t('The Local Contexts project directory page for %protocol_name has been updated.', ['%protocol_name' => $group->getName()]));
    $group->set('field_local_contexts_description', $form_state->getValue('description'));
    $group->save();
  }
}
