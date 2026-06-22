<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\ExportChildResolver;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for adding a single node to an export list.
 */
class ExportListAddNodeForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ExportChildResolver $childResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mukurtu_export.child_resolver'),
    );
  }

  public function getFormId(): string {
    return 'mukurtu_export_add_node_to_list';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('node', $node);

    $form['info'] = [
      '#markup' => $this->t('Adding <em>%title</em> to an export list.', ['%title' => $node->label()]),
    ];

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

    // For aggregative types, offer to include child items.
    if (in_array($node->bundle(), ['collection', 'word_list'])) {
      $children = $this->childResolver->getChildEntities($node);
      $child_count = array_sum(array_map('count', $children));
      if ($child_count > 0) {
        $form['include_children'] = [
          '#type' => 'checkbox',
          '#title' => $this->formatPlural(
            $child_count,
            'Also include 1 child item from this @bundle',
            'Also include @count child items from this @bundle',
            ['@bundle' => $node->bundle() === 'collection' ? $this->t('collection') : $this->t('word list')]
          ),
          '#default_value' => FALSE,
        ];

        // For collections with nested sub-collections, offer a recursive option.
        if ($node->bundle() === 'collection') {
          $recursive_children = $this->childResolver->getChildEntitiesRecursive($node);
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

    // For original records: three-way radio for community record selection.
    $community_records = $this->childResolver->getAccessibleCommunityRecords($node);
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
    $original_record = $this->childResolver->getOriginalRecord($node);
    if ($original_record) {
      $form['include_original_record'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Also include the original record: @title', ['@title' => $original_record->label()]),
        '#default_value' => FALSE,
      ];
      $form_state->set('original_record', $original_record);
    }

    // For multipage item pages: pre-checked list of all accessible pages.
    $mpi_pages = $this->childResolver->getMultipagePages($node);
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

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to List'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('view.mukurtu_manage_all_content.mukurtu_manage_content'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (empty($new_name) && empty($form_state->getValue('export_list_id'))) {
      $form_state->setErrorByName('export_list_id', $this->t('Select an export list or enter a name for a new one.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->get('node');

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

    $items = $list->getItems();
    $items['node'][$node->id()] = $node->id();

    if ($form_state->getValue('include_children_recursive')) {
      foreach ($this->childResolver->getChildEntitiesRecursive($node) as $child_type => $child_ids) {
        $items[$child_type] = ($items[$child_type] ?? []) + $child_ids;
      }
    }
    elseif ($form_state->getValue('include_children')) {
      foreach ($this->childResolver->getChildEntities($node) as $child_type => $child_ids) {
        $items[$child_type] = ($items[$child_type] ?? []) + $child_ids;
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

    // Also include the parent multipage_item entity when any pages are selected.
    if (!empty(array_filter($form_state->getValue('multipage_pages') ?? []))) {
      $mpi = $this->childResolver->getMultipageEntity($node);
      if ($mpi) {
        $mpi_id = (int) $mpi->id();
        $items['multipage_item'][$mpi_id] = $mpi_id;
      }
    }

    $list->setItems($items)->save();

    $this->messenger()->addStatus($this->t('%title added to export list %label.', [
      '%title' => $node->label(),
      '%label' => $list->label(),
    ]));

    $form_state->setRedirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
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

}
