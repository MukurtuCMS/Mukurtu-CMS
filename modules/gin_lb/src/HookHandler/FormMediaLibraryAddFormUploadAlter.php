<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Hook implementation.
 */
class FormMediaLibraryAddFormUploadAlter {

  /**
   * Hook implementation.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $formId
   *   The form ID.
   */
  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $form['#attributes']['class'][] = 'media-library-add-form--upload';
    if (isset($form['container']['upload'])) {
      // Set this flag, so we can prevent the details element from being added
      // in \Drupal\claro\ClaroPreRender::managedFile.
      $form['container']['upload']['#do_not_wrap_in_details'] = TRUE;
    }
    if (isset($form['container'])) {
      $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
    }
  }

}
