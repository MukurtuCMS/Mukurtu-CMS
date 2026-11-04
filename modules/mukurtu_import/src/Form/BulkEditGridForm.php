<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_import\BulkEdit\GridCellText;
use Drupal\mukurtu_import\BulkEdit\GridColumns;
use Drupal\mukurtu_import\BulkEdit\GridRowSaver;
use Drupal\views\Views;

/**
 * The per-item bulk edit grid: one row per selected item, own values each.
 *
 * Reached through the "Edit items one by one" bulk action, which leaves the
 * selection in VBO's tempstore. Cells take the CSV import's text, and each
 * changed row is saved through the importer (GridRowSaver). Rows that fail
 * stay in the grid with their errors; the rest are saved.
 *
 * @see \Drupal\mukurtu_import\Plugin\Action\MukurtuEditItemsGridAction
 */
class BulkEditGridForm extends FormBase {

  /**
   * The most items the grid opens at once.
   */
  public const MAX_ITEMS = 50;

  /**
   * Field types edited in a multi-line box.
   */
  protected const LONG_TYPES = ['string_long', 'text', 'text_long', 'text_with_summary'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_bulk_edit_grid_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $view_id = NULL, ?string $display_id = NULL) {
    $data = $this->selection((string) $view_id, (string) $display_id);
    $columns = $view_id && function_exists('_mukurtu_core_bulk_edit_columns') ? _mukurtu_core_bulk_edit_columns($view_id) : NULL;
    if (!$data || !$columns) {
      return $this->stale($form, $data);
    }

    $count = $this->selectedCount($data);
    if ($count > self::MAX_ITEMS) {
      $form['too_many'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You selected @count items. Edit up to 50 at a time: narrow the selection and try again.', ['@count' => $count]),
      ];
      return $this->addBackLink($form, $data);
    }

    $items = $form_state->get('grid_items') ?? $this->loadItems($data);
    $form_state->set('grid_items', $items);
    if (!$items) {
      return $this->stale($form, $data);
    }
    $remaining = $form_state->get('grid_remaining');
    if (is_array($remaining)) {
      $items = array_intersect_key($items, array_flip($remaining));
    }
    $loaded = $this->loadTranslations($items);

    $first = reset($loaded);
    $type = $first->bundle();
    $grid = new GridColumns($columns);
    $available = $grid->available($type);
    $fields = $grid->selected($type, $this->currentUser());
    $form_state->set('grid_context', [$columns->viewId(), $type]);

    $form['#title'] = $this->formatPlural(count($form_state->get('grid_items')), 'Edit 1 item one by one', 'Edit @count items one by one');
    $form['#attributes']['class'][] = 'mukurtu-bulk-edit-grid';
    $form['#attached']['library'][] = 'mukurtu_import/bulk_edit_grid';
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_columns';

    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Each row is one item. Change any cells, then save. Values use the same format as a CSV import: separate multiple values with a semicolon (;).'),
    ];

    $this->buildColumnsPanel($form, $form_state, $available, $fields);
    $errors = $form_state->get('grid_errors') ?? [];
    if ($errors) {
      $form['error_summary'] = $this->errorSummary($errors, $loaded, $available);
    }

    // Each cell's starting text, kept across rebuilds so a failed row is
    // compared with its stored values, not with what the editor typed.
    $originals = $form_state->get('grid_originals') ?? [];
    $cells = new GridCellText();
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($first->getEntityTypeId(), $type);

    $header = ['item' => $this->t('Item')];
    foreach ($fields as $field) {
      $header[$field] = ['data' => $this->columnHeader($field, (string) $available[$field], $definitions)];
    }
    $form['grid'] = [
      '#type' => 'table',
      '#caption' => $this->t('Selected items'),
      '#header' => $header,
      '#tree' => TRUE,
      '#attributes' => ['class' => ['mukurtu-bulk-edit-grid__table']],
    ];

    foreach ($loaded as $key => $item) {
      $label = $item->label();
      $form['grid'][$key]['item'] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $item->toUrl(),
        '#suffix' => $item->isDefaultTranslation() ? '' : ' <span class="mukurtu-bulk-edit-grid__language">' . Html::escape($item->language()->getName()) . '</span>',
      ];
      foreach ($fields as $field) {
        $originals[$key][$field] ??= $cells->text($item, $field);
        $definition = $definitions[$field];

        // Fields shared by all translations are edited on the original row.
        if (!$item->isDefaultTranslation() && !$definition->isTranslatable()) {
          $form['grid'][$key][$field] = [
            'value' => ['#plain_text' => $originals[$key][$field]],
            'note' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => ['class' => ['mukurtu-bulk-edit-grid__note']],
              '#value' => $this->t('Shared by all translations. Edit it on the original-language row.'),
            ],
          ];
          continue;
        }

        $id = $this->cellId($key, $field);
        $element = [
          '#type' => in_array($definition->getType(), self::LONG_TYPES, TRUE) ? 'textarea' : 'textfield',
          '#title' => $this->t('@field for @item', ['@field' => $available[$field], '@item' => $label]),
          '#title_display' => 'invisible',
          '#default_value' => $originals[$key][$field],
          '#id' => $id,
        ];
        if ($element['#type'] === 'textarea') {
          $element['#rows'] = 2;
        }
        else {
          // Multi-value cells can be long; the field validates its own length.
          $element['#maxlength'] = NULL;
          $element['#size'] = 20;
        }
        $cell_errors = $errors[$key][$field] ?? [];
        if ($cell_errors) {
          $element['#attributes']['aria-invalid'] = 'true';
          $element['#attributes']['aria-describedby'] = "$id-error";
          $element['#attributes']['class'][] = 'error';
          $messages = implode('<br>', array_map([Html::class, 'escape'], $cell_errors));
          $element['#suffix'] = '<div id="' . $id . '-error" class="form-item__error-message">' . $messages . '</div>';
        }
        $form['grid'][$key][$field] = $element;
      }
    }
    $form_state->set('grid_originals', $originals);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
      '#button_type' => 'primary',
    ];
    // A button, not a link, so leaving also clears the selection; otherwise
    // the list would show these items still selected.
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $items = $form_state->get('grid_items') ?? [];
    $remaining = $form_state->get('grid_remaining');
    if (is_array($remaining)) {
      $items = array_intersect_key($items, array_flip($remaining));
    }
    $originals = $form_state->get('grid_originals') ?? [];
    $values = (array) $form_state->getValue('grid');
    [, $type] = $form_state->get('grid_context');
    $loaded = $this->loadTranslations($items);
    $first = reset($loaded);
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($first->getEntityTypeId(), $type);

    $saver = new GridRowSaver();
    $saved = 0;
    $errors = [];
    foreach ($loaded as $key => $item) {
      $changes = [];
      foreach ((array) ($values[$key] ?? []) as $field => $text) {
        if ($field === 'item' || !is_string($text) || !array_key_exists($field, $originals[$key] ?? [])) {
          continue;
        }
        if (trim($text) !== trim($originals[$key][$field])) {
          $changes[$field] = trim($text);
        }
      }
      if (!$changes) {
        continue;
      }
      $labels = array_map(fn($field) => (string) $definitions[$field]->getLabel(), array_keys($changes));
      // Views Bulk Edit's own wording, so both kinds of bulk edit read alike
      // in an item's history.
      $log = (string) $this->formatPlural(count($labels), 'Edited as a part of bulk operation. Field changed: @fields', 'Edited as a part of bulk operation. Fields changed: @fields', ['@fields' => implode(', ', $labels)]);
      $row_errors = $saver->save($item, $changes, $log);
      if ($row_errors) {
        $errors[$key] = $row_errors;
      }
      else {
        $saved++;
      }
    }

    if ($saved) {
      $this->messenger()->addStatus($this->formatPlural($saved, 'Saved 1 item.', 'Saved @count items.'));
    }
    if ($errors) {
      $this->messenger()->addError($this->formatPlural(count($errors), "1 item couldn't be saved. Fix the values below and save again.", "@count items couldn't be saved. Fix the values below and save again."));
      $form_state->set('grid_errors', $errors);
      $form_state->set('grid_remaining', array_keys($errors));
      $form_state->setRebuild();
      return;
    }
    if (!$saved) {
      $this->messenger()->addStatus($this->t('Nothing was changed.'));
    }
    $this->finish($form_state);
  }

  /**
   * Clears the selection and returns to the list without saving.
   */
  public function cancel(array &$form, FormStateInterface $form_state): void {
    $this->finish($form_state);
  }

  /**
   * Saves the grid's column choice and shows the grid again.
   */
  public function applyColumns(array &$form, FormStateInterface $form_state): void {
    [$view_id, $type] = $form_state->get('grid_context');
    $columns = _mukurtu_core_bulk_edit_columns($view_id);
    $chosen = array_keys(array_filter((array) $form_state->getValue(['grid_columns', 'fields'], [])));
    (new GridColumns($columns))->save($type, $this->currentUser(), $chosen);
    $this->messenger()->addStatus($this->t('Columns updated.'));
    $form_state->setRebuild();
  }

  /**
   * Ticks every field in the "Columns" panel, or none, without saving.
   */
  public function pickColumns(array &$form, FormStateInterface $form_state): void {
    $input = $form_state->getUserInput();
    $options = array_keys($form['grid_columns']['fields']['#options']);
    $all = $form_state->getTriggeringElement()['#name'] === 'grid_columns_select_all';
    $input['grid_columns']['fields'] = $all ? array_combine($options, $options) : [];
    $form_state->setUserInput($input);
    $form_state->set('grid_columns_open', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Adds the "Columns" panel.
   */
  protected function buildColumnsPanel(array &$form, FormStateInterface $form_state, array $available, array $fields): void {
    $label_key = array_key_first($available);
    $options = array_map('strval', array_diff_key($available, [$label_key => TRUE]));
    $form['grid_columns'] = [
      '#type' => 'details',
      '#title' => $this->t('Columns'),
      '#open' => (bool) $form_state->get('grid_columns_open'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['mukurtu-bulk-edit-columns']],
    ];
    $form['grid_columns']['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Choose which fields to edit. Your choice is saved for next time.'),
    ];
    foreach (['select_all' => $this->t('Select all'), 'clear_all' => $this->t('Clear all')] as $key => $label) {
      $form['grid_columns']['pick'][$key] = [
        '#type' => 'submit',
        '#name' => "grid_columns_$key",
        '#value' => $label,
        '#submit' => ['::pickColumns'],
        '#limit_validation_errors' => [],
      ];
    }
    $form['grid_columns']['pick'] += ['#type' => 'container', '#attributes' => ['class' => ['mukurtu-bulk-edit-grid__pick']]];
    $form['grid_columns']['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Fields'),
      '#options' => $options,
      '#default_value' => array_values(array_intersect(array_keys($options), $fields)),
    ];
    $form['grid_columns']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply columns'),
      '#submit' => ['::applyColumns'],
      '#limit_validation_errors' => [['grid_columns']],
    ];
  }

  /**
   * Returns a column header: the field label and its CSV format.
   */
  protected function columnHeader(string $field, string $label, array $definitions): array {
    $header = ['label' => ['#plain_text' => $label]];
    $plugin = \Drupal::service('plugin.manager.mukurtu_import_field_process')->getInstance(['field_definition' => $definitions[$field]]);
    $format = $plugin ? $plugin->getFormatDescription($definitions[$field]) : '';
    if ((string) $format !== '') {
      $header['format'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['mukurtu-bulk-edit-grid__format']],
        '#value' => $format,
      ];
    }
    return $header;
  }

  /**
   * Returns the error summary: each problem, linked to its cell.
   */
  protected function errorSummary(array $errors, array $loaded, array $available): array {
    $links = [];
    foreach ($errors as $key => $fields) {
      $item = $loaded[$key] ?? NULL;
      if (!$item) {
        continue;
      }
      foreach ($fields as $field => $messages) {
        foreach ($messages as $message) {
          $text = $this->t('@item: @message', ['@item' => $item->label(), '@message' => $message]);
          $links[] = $field !== '' && isset($available[$field])
            ? ['#type' => 'link', '#title' => $text, '#url' => Url::fromUserInput('#' . $this->cellId($key, $field))]
            : ['#plain_text' => $text];
        }
      }
    }
    return [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['class' => ['mukurtu-bulk-edit-grid__errors']],
      '#wrapper_attributes' => ['role' => 'alert'],
    ];
  }

  /**
   * Returns a cell's HTML ID.
   */
  protected function cellId(string $key, string $field): string {
    return Html::getId("grid-$key-$field");
  }

  /**
   * Returns the editor's saved VBO selection for a view display.
   */
  protected function selection(string $view_id, string $display_id): ?array {
    if ($view_id === '' || $display_id === '') {
      return NULL;
    }
    $data = \Drupal::service('tempstore.private')
      ->get("views_bulk_operations_{$view_id}_{$display_id}")
      ->get((string) $this->currentUser()->id());
    return is_array($data) && !empty($data['action_id']) ? $data : NULL;
  }

  /**
   * Returns how many items are selected.
   */
  protected function selectedCount(array $data): int {
    $listed = count($data['list'] ?? []);
    return empty($data['exclude_mode']) ? $listed : max(0, (int) ($data['total_results'] ?? 0) - $listed);
  }

  /**
   * Returns the selected items as [entity type, ID, langcode], keyed by row.
   *
   * With every result selected, VBO lists the excluded rows instead, so the
   * view runs again to find the rest.
   */
  protected function loadItems(array $data): array {
    $rows = [];
    if (empty($data['exclude_mode'])) {
      foreach ($data['list'] ?? [] as $item) {
        [, $langcode, $entity_type_id, $id] = $item + [NULL, NULL, NULL, NULL];
        if ($entity_type_id && $id) {
          $rows["$id-$langcode"] = [$entity_type_id, (string) $id, (string) $langcode];
        }
      }
    }
    else {
      $excluded = [];
      foreach ($data['list'] ?? [] as $item) {
        $excluded[($item[3] ?? '') . '-' . ($item[1] ?? '')] = TRUE;
      }
      $view = Views::getView($data['view_id']);
      $view->setDisplay($data['display_id']);
      $view->setArguments($data['arguments'] ?? []);
      $view->setExposedInput($data['exposed_input'] ?? []);
      $view->setItemsPerPage(0);
      $view->execute();
      $view_data = \Drupal::service('views_bulk_operations.data');
      $view_data->init($view, $view->getDisplay(), $data['relationship_id'] ?? 'none');
      foreach ($view->result as $result) {
        $entity = $view_data->getEntity($result);
        if ($entity) {
          $key = $entity->id() . '-' . $entity->language()->getId();
          if (!isset($excluded[$key])) {
            $rows[$key] = [$entity->getEntityTypeId(), (string) $entity->id(), $entity->language()->getId()];
          }
        }
      }
    }

    // Only items the editor can edit; the bulk edit page offers no others.
    $loaded = $this->loadTranslations($rows);
    return array_intersect_key($rows, array_filter($loaded, fn($item) => $item->access('update')));
  }

  /**
   * Loads each row's item in the row's language.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Items keyed by row.
   */
  protected function loadTranslations(array $rows): array {
    $loaded = [];
    foreach ($rows as $key => [$entity_type_id, $id, $langcode]) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
      $storage->resetCache([$id]);
      $entity = $storage->load($id);
      if ($entity instanceof ContentEntityInterface) {
        $loaded[$key] = $langcode && $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
      }
    }
    return $loaded;
  }

  /**
   * Shows that the selection is gone, the way VBO's own steps do.
   *
   * @see \Drupal\mukurtu_core\Hook\StaleBulkActionFormHooks
   */
  protected function stale(array $form, ?array $data): array {
    $form['mukurtu_stale_selection'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This action has already run, or your selection has expired. Nothing was changed.'),
    ];
    return $this->addBackLink($form, $data);
  }

  /**
   * Adds the "Select items again" link back to the list.
   */
  protected function addBackLink(array $form, ?array $data): array {
    $url = $data['redirect_url'] ?? NULL;
    if ($url instanceof Url && $url->access()) {
      $form['mukurtu_select_again'] = [
        '#type' => 'link',
        '#title' => $this->t('Select items again'),
        '#url' => $url,
      ];
    }
    return $form;
  }

  /**
   * Clears the selection and returns to the list.
   */
  protected function finish(FormStateInterface $form_state): void {
    $view_id = $form_state->getBuildInfo()['args'][0] ?? '';
    $display_id = $form_state->getBuildInfo()['args'][1] ?? '';
    $store = \Drupal::service('tempstore.private')->get("views_bulk_operations_{$view_id}_{$display_id}");
    $data = $store->get((string) $this->currentUser()->id());
    $store->delete((string) $this->currentUser()->id());
    if (($data['redirect_url'] ?? NULL) instanceof Url) {
      $form_state->setRedirectUrl($data['redirect_url']);
    }
  }

}
