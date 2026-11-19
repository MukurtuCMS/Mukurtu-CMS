<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Explains a stale bulk action page instead of failing on submit.
 *
 * VBO deletes the saved selection when an action runs. Going back to its
 * configure or confirmation page shows the browser's copy, and submitting
 * it throws "The "" plugin does not exist", because VBO's handlers assume
 * the selection is still there.
 */
class StaleBulkActionFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for VBO's configure form.
   */
  #[Hook('form_views_bulk_operations_configure_action_alter')]
  public function configureActionAlter(array &$form, FormStateInterface $form_state): void {
    $this->explainStaleForm($form, $form_state);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for VBO's confirmation form.
   */
  #[Hook('form_views_bulk_operations_confirm_action_alter')]
  public function confirmActionAlter(array &$form, FormStateInterface $form_state): void {
    $this->explainStaleForm($form, $form_state);
  }

  /**
   * Replaces a form with no saved selection by a message and a link back.
   */
  protected function explainStaleForm(array &$form, FormStateInterface $form_state): void {
    // VBO's buildForm() stores the selection only when there is one.
    if ($form_state->get('views_bulk_operations')) {
      return;
    }

    unset($form['#markup']);
    // Nothing to validate or run; VBO's handlers would throw.
    $form['#validate'] = [];
    $form['#submit'] = [];
    $form['#cache']['max-age'] = 0;
    $form['mukurtu_stale_selection'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This action has already run, or your selection has expired. Nothing was changed.'),
    ];

    [$view_id, $display_id] = $form_state->getBuildInfo()['args'] + [NULL, NULL];
    $url = $this->listUrl($view_id, $display_id);
    if ($url) {
      $form['mukurtu_select_again'] = [
        '#type' => 'link',
        '#title' => $this->t('Select items again'),
        '#url' => $url,
      ];
    }
  }

  /**
   * Returns the list a selection came from, if the editor can reach it.
   *
   * Loading the list saves an empty selection with the list's URL, filters
   * included; that's the state a finished action leaves behind.
   */
  protected function listUrl(?string $view_id, ?string $display_id): ?Url {
    if (!$view_id || !$display_id) {
      return NULL;
    }
    $data = \Drupal::service('tempstore.private')
      ->get('views_bulk_operations_' . $view_id . '_' . $display_id)
      ->get((string) \Drupal::currentUser()->id());
    $url = $data['redirect_url'] ?? NULL;
    return $url instanceof Url && $url->access() ? $url : NULL;
  }

}
