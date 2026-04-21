<?php

namespace Drupal\term_merge_manager\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\term_merge_manager\Entity\TermMergeFrom;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Term merge into entities.
 *
 * @ingroup term_merge_manager
 */
class TermMergeIntoDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $from = TermMergeFrom::loadByMergeId($entity->id());
    if (!empty($from) && is_array($from)) {
      foreach ($from as $id) {
        TermMergeFrom::load($id)->delete();
      }
    }
  }

}
