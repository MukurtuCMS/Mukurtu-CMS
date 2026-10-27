<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Columns" panel above the bulk edit table.
 *
 * Kept apart from the view's filters so choosing columns doesn't re-run the
 * search. Submitting saves the choice and reloads the page with the current
 * filters.
 */
class BulkEditColumnsForm extends FormBase {

  public function __construct(
    protected readonly BulkEditColumns $columns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_core.bulk_edit_columns'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_bulk_edit_columns_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $type = NULL) {
    $available = array_map('strval', $this->columns->available($type));
    $selected = $this->columns->selected($type, $this->currentUser());
    $item = array_intersect_key($available, array_flip(BulkEditColumns::ITEM_COLUMNS));
    $fields = array_diff_key($available, $item);

    $form['#attributes']['class'][] = 'mukurtu-bulk-edit-columns';
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_columns';

    $form['picker'] = [
      '#type' => 'details',
      '#title' => $this->t('Columns'),
      '#open' => FALSE,
    ];
    // Plain content rather than the details' #description: the admin theme
    // points aria-describedby at an id its template never prints.
    $form['picker']['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Choose which columns to show. Your choice is saved for next time.'),
    ];
    $form['picker']['item'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Item details'),
      '#options' => $item,
      '#default_value' => array_intersect($selected, array_keys($item)),
    ];
    if ($fields) {
      $form['picker']['fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Fields'),
        '#options' => $fields,
        '#default_value' => array_intersect($selected, array_keys($fields)),
      ];
    }
    $form['picker']['actions'] = ['#type' => 'actions'];
    $form['picker']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply columns'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getBuildInfo()['args'][0];
    $chosen = array_merge(
      array_keys(array_filter((array) $form_state->getValue('item', []))),
      array_keys(array_filter((array) $form_state->getValue('fields', []))),
    );
    // With no redirect set, the form returns to this page, filters included.
    $this->columns->save($type, $this->currentUser(), $chosen);
    $this->messenger()->addStatus($this->t('Columns updated.'));
  }

}
