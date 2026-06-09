<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_export\ExportChildResolver;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface;
use Drupal\views_bulk_operations\Traits\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for the "Add to export list" VBO bulk action.
 *
 * VBO redirects here (via confirm_form_route_name on AddToExportListAction)
 * before executing the action. This form reads selected entities directly from
 * the VBO tempstore, lets the user pick or create an export list, then adds
 * the entities and clears the VBO selection.
 */
class ExportListAddItemsForm extends FormBase {

  use ViewsBulkOperationsFormTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly ViewsBulkOperationsActionProcessorInterface $actionProcessor,
    protected readonly ExportChildResolver $childResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('views_bulk_operations.processor'),
      $container->get('mukurtu_export.child_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_export_add_items_to_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $view_id = NULL, ?string $display_id = NULL): array {
    $form_data = $this->getFormData($view_id, $display_id);

    if (!\array_key_exists('action_id', $form_data)) {
      $this->messenger()->addWarning($this->t('No items are staged for export.'));
      $destination = $this->getRequest()->query->get('destination');
      $destination
        ? $form_state->setRedirectUrl(Url::fromUserInput($destination))
        : $form_state->setRedirect('entity.export_list.collection');
      return $form;
    }

    $form_state->set('views_bulk_operations', $form_data);

    $form['list'] = $this->getListRenderable($form_data);

    // Export list selector.
    $uid = $this->currentUser()->id();
    $storage = $this->entityTypeManager->getStorage('export_list');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $or = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $list_ids = $query->condition($or)->sort('label')->execute();
    $lists = $storage->loadMultiple($list_ids);

    $options = [];
    foreach ($lists as $list) {
      $options[$list->id()] = $list->label();
    }

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Add to export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#required' => FALSE,
    ];

    $form['new_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Or create a new list'),
      '#description' => $this->t('If provided, a new list will be created with this name.'),
      '#maxlength' => 255,
    ];

    // For aggregative types in the selection, offer to include child items.
    $child_count = 0;
    foreach ($form_data['list'] as $item) {
      if ($item[2] !== 'node') {
        continue;
      }
      $node = $this->entityTypeManager->getStorage('node')->load($item[3]);
      if ($node && in_array($node->bundle(), ['collection', 'word_list'])) {
        $children = $this->childResolver->getChildEntities($node);
        $child_count += array_sum(array_map('count', $children));
      }
    }
    if ($child_count > 0) {
      $form['include_children'] = [
        '#type' => 'checkbox',
        '#title' => $this->formatPlural(
          $child_count,
          'Also include 1 child item from collections and word lists in this selection',
          'Also include @count child items from collections and word lists in this selection',
        ),
        '#default_value' => FALSE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to List'),
      '#button_type' => 'primary',
    ];
    $this->addCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (empty($new_name) && empty($form_state->getValue('export_list_id'))) {
      $error = $this->t('Select an export list or enter a name for a new one.');
      $form_state->setErrorByName('export_list_id', $error);
      $form_state->setErrorByName('new_list_name', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_data = $form_state->get('views_bulk_operations');

    // Resolve or create the export list.
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (!empty($new_name)) {
      $list = $this->entityTypeManager->getStorage('export_list')->create([
        'label' => $new_name,
        'uid' => $this->currentUser()->id(),
        'site_wide' => FALSE,
      ]);
      $list->save();
    }
    else {
      $list = $this->entityTypeManager->getStorage('export_list')
        ->load($form_state->getValue('export_list_id'));
    }

    if (!$list) {
      $this->messenger()->addError($this->t('Could not find or create the export list.'));
      return;
    }

    // Parse selected entity IDs from VBO bulk form keys.
    // Each list item is [base_field_value, langcode, entity_type_id, entity_id].
    $by_type = [];
    foreach ($form_data['list'] as $item) {
      $entity_type = $item[2];
      $entity_id = $item[3];
      $by_type[$entity_type][$entity_id] = $entity_id;
    }

    // Add entities to the export list (read-modify-write).
    $items = $list->getItems();
    foreach ($by_type as $entity_type => $ids) {
      $items[$entity_type] = $items[$entity_type] ?? [];
      foreach ($ids as $id) {
        $items[$entity_type][$id] = $id;
      }
    }

    // Optionally include child items from collections and word lists.
    if ($form_state->getValue('include_children')) {
      foreach ($by_type['node'] ?? [] as $node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
        if (!$node) {
          continue;
        }
        foreach ($this->childResolver->getChildEntities($node) as $child_type => $child_ids) {
          $items[$child_type] = ($items[$child_type] ?? []) + $child_ids;
        }
      }
    }

    $list->setItems($items)->save();

    $count = array_sum(array_map('count', $by_type));
    $this->messenger()->addStatus($this->t('@count item(s) added to export list %label.', [
      '@count' => $count,
      '%label' => $list->label(),
    ]));

    // Clean up the VBO tempstore and redirect back to the view.
    $this->deleteTempstoreData($form_data['view_id'], $form_data['display_id']);
    $form_state->setRedirectUrl($form_data['redirect_url']);
  }

}
