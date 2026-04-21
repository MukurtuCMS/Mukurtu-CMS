<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Hook implementation.
 */
class FormMediaLibraryAddFormOembedAlter {

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
    $form['#attributes']['class'][] = 'media-library-add-form--oembed';

    // If no media items have been added yet, add a couple of styling classes
    // to the initial URL form.
    if (isset($form['container'])) {
      $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
      $form['container']['url']['#attributes']['class'][] = 'media-library-add-form-oembed-url';
      $form['container']['submit']['#attributes']['class'][] = 'media-library-add-form-oembed-submit';
    }
  }

}
