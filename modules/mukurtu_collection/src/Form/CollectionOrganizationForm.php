<?php

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Mukurtu Collections form.
 */
class CollectionOrganizationForm extends FormBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager)
  {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $collections = NULL, $available_collections = []) {
    $form['collections'] = [
      '#type' => 'table',
      '#caption' => $this->t('Collection Organization'),
      '#header' => [
        $this->t('Collection'),
        $this->t('Weight'),
        $this->t('Parent'),
      ],
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

    foreach ($collections as $id => $info) {
      $collection = $info['collection'];
      $form['collections'][$id]['#weight'] = $info['weight'];
      $form['collections'][$id]['#attributes']['class'][] = 'draggable';
      $form['collections'][$id]['label'] = [
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

      $form['collections'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $collection->getTitle()]),
        '#title_display' => 'invisible',
        '#default_value' => $info['weight'] ?? 0,
        '#attributes' => ['class' => ['field-weight']],
      ];

      $form['collections'][$id]['parent'] = [
        '#type' => 'weight',
        '#title' => $this->t('Parent for @title', ['@title' => $collection->getTitle()]),
        '#title_display' => 'invisible',
        '#default_value' => $info['parent'] ?? 0,
        '#attributes' => ['class' => ['field-parent']],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
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

    foreach ($newOrganization as $info) {
      $id = reset($info['label']);
      /** @var \Drupal\mukurtu_collection\Entity\Collection $collection */
      if ($collection = $this->entityTypeManager->getStorage('node')->load($id)) {
        if ($info['parent'] == "0") {
          $collection->removeAsChildCollection();
        }

        /** @var \Drupal\mukurtu_collection\Entity\Collection $parent */
        $parent = $this->entityTypeManager->getStorage('node')->load($info['parent']);
        if ($collection && $parent) {
          if ($collection->getParentCollectionId() != $parent->id()) {
            $collection->removeAsChildCollection();
            $parent->addChildCollection($collection)->save();
          }
          // Need to resolve weights somehow...
        }
      }
    }
    //
  }

}
