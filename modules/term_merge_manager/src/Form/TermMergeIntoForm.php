<?php

namespace Drupal\term_merge_manager\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Term merge into edit forms.
 *
 * @ingroup term_merge_manager
 */
class TermMergeIntoForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\term_merge_manager\Entity\TermMergeInto $entity */
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Term merge into.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Term merge into.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.term_merge_into.canonical', ['term_merge_into' => $entity->id()]);
  }

}
