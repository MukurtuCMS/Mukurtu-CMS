<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for editing an existing Export List.
 */
class ExportListEditForm extends ExportListFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    $entity = $this->entity;

    $form['actions']['submit']['#value'] = $this->t('Save');

    $form['actions']['export'] = [
      '#type' => 'link',
      '#title' => $this->t('Export'),
      '#url' => \Drupal\Core\Url::fromRoute('mukurtu_export.start_list_export', ['export_list' => $entity->id()]),
      '#attributes' => ['class' => ['button']],
      '#gin_action_item' => TRUE,
      '#weight' => 5,
    ];
    $items = $entity->getItems();

    $options = [];
    foreach ($items as $entity_type_id => $ids) {
      if (empty($ids)) {
        continue;
      }
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
      $entities = $storage->loadMultiple(array_keys($ids));
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
      $type_label = $storage->getEntityType()->getLabel();
      foreach ($entities as $item) {
        $key = $entity_type_id . ':' . $item->id();
        $url = $item->hasLinkTemplate('canonical') ? $item->toUrl('canonical') : NULL;
        $options[$key] = [
          'label' => $url
            ? ['data' => ['#type' => 'link', '#title' => $item->label(), '#url' => $url]]
            : $item->label(),
          'type' => $type_label,
          'bundle' => $bundle_info[$item->bundle()]['label'] ?? $item->bundle(),
        ];
      }
    }

    $form['items_table'] = [
      '#type' => 'tableselect',
      '#caption' => $this->t('Content in this list'),
      '#header' => [
        'label' => $this->t('Title'),
        'type' => $this->t('Type'),
        'bundle' => $this->t('Content Type'),
      ],
      '#options' => $options,
      '#empty' => $this->t('This list has no items yet.'),
      '#weight' => 30,
    ];

    if (!empty($options)) {
      $form['remove_selected'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove selected'),
        '#submit' => ['::submitRemoveItems'],
        '#limit_validation_errors' => [['items_table']],
        '#weight' => 31,
      ];
    }

    return $form;
  }

  /**
   * Submit handler: remove checked items from the list.
   */
  public function submitRemoveItems(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    $entity = $this->entity;
    $selected = array_filter($form_state->getValue('items_table') ?? []);

    if (empty($selected)) {
      $this->messenger()->addWarning($this->t('No items were selected.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $items = $entity->getItems();
    foreach (array_keys($selected) as $key) {
      [$entity_type_id, $entity_id] = explode(':', $key, 2);
      unset($items[$entity_type_id][$entity_id]);
    }
    $entity->setItems($items);
    $entity->save();

    $count = count($selected);
    $this->messenger()->addStatus($this->formatPlural($count,
      'Removed 1 item from the list.',
      'Removed @count items from the list.'
    ));
    $form_state->setRebuild(TRUE);
  }


}
