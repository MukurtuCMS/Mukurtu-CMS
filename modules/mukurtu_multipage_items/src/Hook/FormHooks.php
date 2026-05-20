<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;

/**
 * Form hook implementations for mukurtu_multipage_items.
 */
class FormHooks {

  /**
   * Implements hook_form_views_exposed_form_alter().
   *
   * Filters the exposed Type dropdown in the multipage_item_browser view to
   * only show content types enabled at /admin/config/mukurtu/multipage-item.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function filterTypeDropdown(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['type'])) {
      return;
    }
    $storage = $form_state->getStorage();
    $view = $storage['view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return;
    }
    if ($view->storage->id() !== 'multipage_item_browser') {
      return;
    }

    $bundles_config = \Drupal::config('mukurtu_multipage_items.settings')->get('bundles_config') ?? [];
    $enabled = array_keys(array_filter($bundles_config));

    if (count($enabled) === 1) {
      $form['type']['#access'] = FALSE;
      return;
    }

    foreach ($form['type']['#options'] as $key => $label) {
      if ($key !== 'All' && !in_array($key, $enabled, TRUE)) {
        unset($form['type']['#options'][$key]);
      }
    }
  }

}
