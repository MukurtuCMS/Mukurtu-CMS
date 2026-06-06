<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\Form\FileUploadForm;

/**
 * Extends the media library file upload form to clear stale user input.
 *
 * When an existing media item is selected in the media library browser but not
 * yet inserted, its protocol data can contaminate the user input for newly
 * created media entity forms. Clearing the 'media' user input before creating
 * new entities prevents cultural protocol checkboxes from being pre-selected.
 *
 * @internal
 *   Form classes are internal.
 */
class MukurtuFileUploadForm extends FileUploadForm {

  /**
   * {@inheritdoc}
   */
  public function uploadButtonSubmit(array $form, FormStateInterface $form_state) {
    // Clear stale 'media' user input to prevent protocol pre-selection on new
    // entities when an existing media item is already in the current selection.
    $user_input = $form_state->getUserInput();
    unset($user_input['media']);
    $form_state->setUserInput($user_input);
    parent::uploadButtonSubmit($form, $form_state);
  }

}
