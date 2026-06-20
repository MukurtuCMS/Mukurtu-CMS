<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_export\ExportChildResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Form for adding a single entity to an export list.
 *
 * Reached via the "Add to export list" link on content pages.
 */
class ExportListAddItemForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ExportChildResolver $childResolver;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExportChildResolver $child_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->childResolver = $child_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mukurtu_export.child_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_add_item_to_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = '', string $entity_id = '') {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      $this->messenger()->addError($this->t('The item could not be found.'));
      return $form;
    }

    $form['entity_type'] = ['#type' => 'hidden', '#value' => $entity_type];
    $form['entity_id'] = ['#type' => 'hidden', '#value' => $entity_id];

    $form['entity_info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Adding <em>@label</em> to an export list.', ['@label' => $entity->label()]),
    ];

    // Load accessible lists for this user.
    $uid = $this->currentUser()->id();
    $storage = $this->entityTypeManager->getStorage('export_list');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $or = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $ids = $query->condition($or)->sort('label')->execute();
    $lists = $storage->loadMultiple($ids);

    $options = [];
    foreach ($lists as $list) {
      $options[$list->id()] = $list->label();
    }
    $options['_new'] = $this->t('Create a new list...');

    $form['export_list_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select an export list'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => array_key_first($options),
    ];

    $form['new_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New list name'),
      '#description' => $this->t('Required when "Create a new list" is selected.'),
      '#maxlength' => 255,
      '#states' => [
        'visible' => [':input[name="export_list_id"]' => ['value' => '_new']],
        'required' => [':input[name="export_list_id"]' => ['value' => '_new']],
      ],
    ];

    // For aggregative types, offer to include child items.
    if ($entity->getEntityTypeId() === 'node' && in_array($entity->bundle(), ['collection', 'word_list'])) {
      $children = $this->childResolver->getChildEntities($entity);
      $child_count = array_sum(array_map('count', $children));
      if ($child_count > 0) {
        $form['include_children'] = [
          '#type' => 'checkbox',
          '#title' => $this->formatPlural(
            $child_count,
            'Also include 1 child item from this @bundle',
            'Also include @count child items from this @bundle',
            ['@bundle' => $entity->bundle() === 'collection' ? $this->t('collection') : $this->t('word list')]
          ),
          '#default_value' => FALSE,
        ];

        // For collections with nested sub-collections, offer a recursive option.
        if ($entity->bundle() === 'collection') {
          $recursive_children = $this->childResolver->getChildEntitiesRecursive($entity);
          $recursive_count = array_sum(array_map('count', $recursive_children));
          $additional_count = $recursive_count - $child_count;
          if ($additional_count > 0) {
            $form['include_children_recursive'] = [
              '#type' => 'checkbox',
              '#title' => $this->formatPlural(
                $additional_count,
                'Also include all items nested within child collections (1 additional item)',
                'Also include all items nested within child collections (@count additional items)',
              ),
              '#default_value' => FALSE,
              '#states' => [
                'visible' => [':input[name="include_children"]' => ['checked' => TRUE]],
              ],
            ];
          }
        }
      }
    }

    // For nodes: add community record and multipage item selection.
    if ($entity instanceof NodeInterface) {
      // For original records: three-way radio for community record selection.
      $community_records = $this->childResolver->getAccessibleCommunityRecords($entity);
      if (!empty($community_records)) {
        $form['community_records_mode'] = [
          '#type' => 'radios',
          '#title' => $this->t('Community records'),
          '#options' => [
            'none' => $this->t('Just this record'),
            'all' => $this->t('This record and all accessible community records'),
            'select' => $this->t('This record and select community records'),
          ],
          '#default_value' => 'none',
        ];
        $cr_options = [];
        foreach ($community_records as $cr) {
          $cr_options[$cr->id()] = $this->getCommunityRecordLabel($cr);
        }
        $form['community_records_select'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select community records'),
          '#options' => $cr_options,
          '#default_value' => array_keys($cr_options),
          '#states' => [
            'visible' => [
              ':input[name="community_records_mode"]' => ['value' => 'select'],
            ],
          ],
        ];
        $form_state->set('community_records', $community_records);
      }

      // For community records: offer to include the original record.
      $original_record = $this->childResolver->getOriginalRecord($entity);
      if ($original_record) {
        $form['include_original_record'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Also include the original record: @title', ['@title' => $original_record->label()]),
          '#default_value' => FALSE,
        ];
        $form_state->set('original_record', $original_record);
      }

      // For multipage item pages: pre-checked list of all accessible pages.
      $mpi_pages = $this->childResolver->getMultipagePages($entity);
      if (!empty($mpi_pages)) {
        $page_options = [];
        foreach ($mpi_pages as $page) {
          $page_options[$page->id()] = $page->label();
        }
        $form['multipage_pages'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Pages in this multipage item'),
          '#options' => $page_options,
          '#default_value' => array_keys($page_options),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to List'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->getReturnUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('export_list_id') === '_new') {
      $name = trim($form_state->getValue('new_list_name') ?? '');
      if (empty($name)) {
        $form_state->setErrorByName('new_list_name', $this->t('Please enter a name for the new list.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $entity_id = $form_state->getValue('entity_id');
    $list_id = $form_state->getValue('export_list_id');

    $storage = $this->entityTypeManager->getStorage('export_list');

    if ($list_id === '_new') {
      /** @var \Drupal\mukurtu_export\Entity\ExportList $list */
      $list = $storage->create([
        'label' => trim($form_state->getValue('new_list_name')),
        'uid' => $this->currentUser()->id(),
        'site_wide' => FALSE,
      ]);
    }
    else {
      /** @var \Drupal\mukurtu_export\Entity\ExportList $list */
      $list = $storage->load($list_id);
    }

    if (!$list) {
      $this->messenger()->addError($this->t('The selected export list could not be found.'));
      return;
    }

    // Add the entity to the list items.
    $items = $list->getItems();
    $items[$entity_type] = $items[$entity_type] ?? [];
    $items[$entity_type][$entity_id] = $entity_id;

    // Optionally include child items (collections, word lists).
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity) {
      if ($form_state->getValue('include_children_recursive')) {
        foreach ($this->childResolver->getChildEntitiesRecursive($entity) as $child_type => $child_ids) {
          $items[$child_type] = ($items[$child_type] ?? []) + $child_ids;
        }
      }
      elseif ($form_state->getValue('include_children')) {
        foreach ($this->childResolver->getChildEntities($entity) as $child_type => $child_ids) {
          $items[$child_type] = ($items[$child_type] ?? []) + $child_ids;
        }
      }
    }

    // Include community records based on radio selection.
    $cr_mode = $form_state->getValue('community_records_mode');
    if ($cr_mode === 'all') {
      foreach ($form_state->get('community_records') ?? [] as $cr) {
        $id = (int) $cr->id();
        $items['node'][$id] = $id;
      }
    }
    elseif ($cr_mode === 'select') {
      foreach ($form_state->getValue('community_records_select') ?? [] as $nid => $checked) {
        if ($checked) {
          $nid = (int) $nid;
          $items['node'][$nid] = $nid;
        }
      }
    }

    // Include the original record if this node is a community record.
    if ($form_state->getValue('include_original_record')) {
      $or = $form_state->get('original_record');
      if ($or) {
        $id = (int) $or->id();
        $items['node'][$id] = $id;
      }
    }

    // Include selected multipage item pages.
    foreach ($form_state->getValue('multipage_pages') ?? [] as $nid => $checked) {
      if ($checked) {
        $nid = (int) $nid;
        $items['node'][$nid] = $nid;
      }
    }

    $list->setItems($items);
    $list->save();

    // Also flag the item for the global flag queue so it appears in the
    // export list views (export_list_content / export_list_media).
    $flag_map = ['node' => 'export_content', 'media' => 'export_media'];
    $flag_id = $flag_map[$entity_type] ?? NULL;
    if ($flag_id) {
      $flag_service = \Drupal::service('flag');
      $flag = $flag_service->getFlagById($flag_id);
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      if ($flag && $entity && !$flag_service->getFlagging($flag, $entity)) {
        try {
          $flag_service->flag($flag, $entity);
        }
        catch (\Exception $e) {
          // Flag may already exist; silently continue.
        }
      }
    }

    $this->messenger()->addStatus($this->t('@label added to export list %list.', [
      '@label' => $this->entityTypeManager->getStorage($entity_type)->load($entity_id)?->label() ?? $entity_id,
      '%list' => $list->label(),
    ]));

    $form_state->setRedirectUrl($this->getReturnUrl());
  }

  /**
   * Builds a display label for a community record using its community names.
   */
  protected function getCommunityRecordLabel(NodeInterface $node): string {
    if (!$node->hasField('field_communities')) {
      return $node->label();
    }
    $names = [];
    foreach ($node->get('field_communities')->referencedEntities() as $community) {
      $names[] = $community->getName();
    }
    return $names ? implode(', ', $names) : $node->label();
  }

  /**
   * Returns the URL to redirect to after submit (or cancel).
   */
  protected function getReturnUrl(): Url {
    $destination = $this->getRequest()->query->get('destination');
    if ($destination && !str_starts_with($destination, '//')) {
      return Url::fromUserInput($destination);
    }
    return Url::fromRoute('entity.export_list.collection');
  }

}
