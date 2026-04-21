<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Hook implementation.
 */
class FormMediaLibraryAddFormAlter {

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
    $form['#attributes']['class'][] = 'media-library-add-form';

    // If there are unsaved media items, apply styling classes to various parts
    // of the form.
    if (isset($form['media'])) {
      $form['#attributes']['class'][] = 'media-library-add-form--with-input';

      // Put a wrapper around the informational message above the unsaved media
      // items.
      $form['description']['#template'] = '<p class="glb-media-library-add-form__description">{{ text }}</p>';
    }
    else {
      $form['#attributes']['class'][] = 'media-library-add-form--without-input';
    }
  }

}
