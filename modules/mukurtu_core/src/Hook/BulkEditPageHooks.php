<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;

/**
 * Adds the column picker to the bulk edit page's filter form.
 *
 * @see _mukurtu_core_bulk_edit_pre_view()
 */
class BulkEditPageHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->get('view');
    if (!$view || $view->id() !== BulkEditColumns::VIEW_ID || empty($view->args[0])) {
      return;
    }

    /** @var \Drupal\mukurtu_core\BulkEdit\BulkEditColumns $columns */
    $columns = \Drupal::service('mukurtu_core.bulk_edit_columns');
    $type = $view->args[0];
    $options = array_map('strval', $columns->available($type));
    $selected = $columns->selected($type, \Drupal::currentUser());

    // Collapsed, so a long list doesn't push the table down. The checkboxes
    // keep their own fieldset legend for screen readers.
    $form['columns_picker'] = [
      '#type' => 'details',
      '#title' => $this->t('Columns'),
      '#open' => FALSE,
      '#weight' => 90,
    ];
    $form['columns_picker']['columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Columns'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Choose which columns to show. Your choice is saved for next time.'),
      '#options' => $options,
      '#default_value' => $selected,
    ];
    // Exposed forms take their values from the query string, where unticked
    // boxes don't appear, so a checkbox's own default is never used. The
    // choice was already saved while the view was set up, so show that.
    $input = $form_state->getUserInput();
    $input['columns'] = array_combine($selected, $selected);
    $form_state->setUserInput($input);
    // Tells the page that the checkboxes were submitted, so an empty choice
    // can be told apart from a first visit.
    $form['columns_submitted'] = [
      '#type' => 'hidden',
      '#value' => 1,
    ];
  }

}
