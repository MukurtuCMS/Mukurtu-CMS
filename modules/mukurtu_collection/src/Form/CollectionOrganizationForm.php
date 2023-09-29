<?php

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_collection\Entity\Collection;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a Mukurtu Collections form.
 */
class CollectionOrganizationForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_collection_collection_organization';
  }

  /**
   * Build a collection organization array from a collection entity.
   */
  protected function getOrganizationFromCollection(Collection $collection) {
    $organization = [];
    $visited = [];
    $s = [[$collection, 0, 0, 0]];
    while(!empty($s)) {
      list($c, $parent_id, $weight, $level) = array_pop($s);
      if (!isset($visited[$c->id()])) {
        $visited[$c->id()] = TRUE;
        $organization[] = ['title' => $c->getTitle(), 'id' => $c->id(), 'collection' => $c, 'parent' => $parent_id, 'weight' => $weight++, 'level' => $level];
        $childWeight = 0;
        $children = $c->getChildCollections();
        $children = array_reverse($children);
        foreach ($children as $child) {
          $childInfo = [$child, $c->id(), $childWeight++, $level + 1];
          array_push($s, $childInfo);
        }
      }
    }

    return $organization;
  }


  /**
   * Build the collection organization array from the form table values.
   */
  protected function getOrganizationFromValues($values) {
    $organization = [];

    if (empty($values)) {
      return $organization;
    }

    $levels = [];
    foreach ($values as $value) {
      if ($collection = $this->entityTypeManager->getStorage('node')->load(reset($value['label']))) {

        if ($value['parent'] == 0) {
          $levels[$collection->id()] = 0;
        } else {
          $levels[$collection->id()] = $levels[(int) $value['parent']] + 1;
        }

        $organization[] = [
          "title" => $collection->getTitle(),
          "id" => $collection->id(),
          "collection" => $collection,
          "parent" => $value['parent'],
          "weight" => $value['weight'],
          "level" => $levels[$collection->id()],
        ];
      }
    }

    return $organization;
  }


  /**
   * Build the collections table element based on a collections organization array.
   */
  protected function buildCollectionsTable($collectionsOrg) {
    $element = [
      '#type' => 'table',
      '#caption' => $this->t('Collection Organization'),
      '#header' => [
        $this->t('Collection'),
        $this->t('Weight'),
        $this->t('Parent'),
      ],
      '#prefix' => '<div id="collections-table">',
      '#suffix' => '</div>',
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'field-parent',
          'subgroup' => 'field-parent',
          'source' => 'collection-id',
          'hidden' => TRUE,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ],
      ]
    ];

    foreach ($collectionsOrg as $id => $info) {
      $collection = $info['collection'];
      $element[$id]['#weight'] = $info['weight'];
      $element[$id]['#attributes']['class'][] = 'draggable';
      $element[$id]['label'] = [
        [
          '#theme' => 'indentation',
          '#size' => $info['level'],
        ],
        [
          '#plain_text' => $collection->getTitle(),
        ],
        [
          '#type' => 'hidden',
          '#title_display' => 'invisible',
          '#value' => $collection->id(),
          '#attributes' => ['class' => ['collection-id']],
        ],
      ];

      $element[$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $collection->getTitle()]),
        '#title_display' => 'invisible',
        '#default_value' => $info['weight'] ?? 0,
        '#attributes' => ['class' => ['field-weight']],
      ];

      $element[$id]['parent'] = [
        '#type' => 'weight',
        '#title' => $this->t('Parent for @title', ['@title' => $collection->getTitle()]),
        '#title_display' => 'invisible',
        '#default_value' => $info['parent'] ?? 0,
        '#attributes' => ['class' => ['field-parent']],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Collection $collection = NULL, $available_collections = []) {
    if (!$collection) {
      return $form;
    }

    $collectionsValue = $form_state->getValue('collections') ?? NULL;
    $collections_organization = $collectionsValue ? $this->getOrganizationFromValues($collectionsValue) : $this->getOrganizationFromCollection($collection);

    $form['add_subcollection'] = [
      '#type' => 'textfield',
      '#title' => t('Add an existing collection as a sub-collection'),
      '#description' => t('Start typing the title of an existing collection. The collection cannot be a sub-collection already. You must have edit access to the collection.'),
      '#autocomplete_route_name' => 'mukurtu_collection.collection_organization_subcollection_autocomplete',
      '#autocomplete_route_parameters' => ['node' => $collection->id()],
    ];

    $form['add'] = [
      "#type" => "submit",
      "#value" => "Add to Organization",
      '#submit' => ['::addCollectionToOrganizationAjax'],
      '#ajax' => [
        'callback' => '::addCollectionToTableCallback',
        'wrapper' => 'collections-table',
      ],
    ];

    $form['collections'] = $this->buildCollectionsTable($collections_organization);

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function addCollectionToTableCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#collections-table', $form['collections']));
    return $response;
  }

  protected function buildOrganizationFromTableCollections($tableCollections) {
    $organization = [];
    foreach ($tableCollections as $tableCollection) {
      if ($id = $tableCollection['label'][2] ?? NULL) {
        $collection = $this->entityTypeManager->getStorage('node')->load($id);

        $organization[] = [
          'title' => $collection->getTitle(),
          'id' => $collection->id(),
          'collection' => $collection,
          'parent' => $tableCollection['parent'],
          'weight' => $tableCollection['weight'],
          'level' => 0,
        ];
      }
    }
    return $organization;
  }

  /**
   * Check if a collection ID is in the table values.
   */
  protected function alreadyInCollectionsTable($collection_id, $table_values) {
    foreach ($table_values as $value) {
      if ($id = reset($value['label']) ?? NULL) {
        if ($id == $collection_id) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public function addCollectionToOrganizationAjax(array &$form, FormStateInterface $form_state) {
    $newCollectionAutocompleteValue = $form_state->getValue('add_subcollection') ?? '';

    $matches = [];
    if (preg_match('/^(.*) \((\d+)\)$/', $newCollectionAutocompleteValue, $matches)) {
      $id = $matches[2];
      if ($collection = $this->entityTypeManager->getStorage('node')->load($id)) {
        // Add the new collection to the values array.
        $collectionValues = $form_state->getValue("collections");
        if (!$this->alreadyInCollectionsTable($id, $collectionValues)) {
          $collectionValues[] = [
            'label' => [2 => $id],
            'weight' => 0,
            'parent' => 0,
          ];
          $form_state->setValue('collections', $collectionValues);

          // Rebuild the collections table.
          $form['collections'] = $this->buildCollectionsTable($this->getOrganizationFromValues($collectionValues));
        }
      }
    }
    $form_state->setRebuild(TRUE);
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $newOrganization = $form_state->getValue('collections');
    $newChildren = [];

    foreach ($newOrganization as $info) {
      $id = reset($info['label']);
      /** @var \Drupal\mukurtu_collection\Entity\Collection $collection */
      if ($collection = $this->entityTypeManager->getStorage('node')->load($id)) {
        if ($info['parent'] == "0") {
          $collection->removeAsChildCollection();

          // Parent === 0 is a "top level" collection which don't have weights,
          // so we can move on.
          continue;
        }

        /** @var \Drupal\mukurtu_collection\Entity\Collection $parent */
        $parent = $this->entityTypeManager->getStorage('node')->load($info['parent']);
        if ($collection && $parent) {
          if ($collection->getParentCollectionId() != $parent->id()) {
            $collection->removeAsChildCollection();
          }

          // Building the list of new parent -> child collections to
          // 1. Resolve ordering (weight)
          // 2. Delay saving until the end so we only save once per entity.
          $newChildren[$parent->id()][] = $collection->id();
        }
      }
    }

    // Save the final child collections.
    foreach ($newChildren as $id => $childCollections) {
      if ($collection = $this->entityTypeManager->getStorage('node')->load($id)) {
        $collection->setChildCollections($childCollections)->save();
      }
    }

    $this->messenger()->addStatus($this->t("Saved new collection organization."));
  }

}
