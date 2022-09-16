<?php

namespace Drupal\mukurtu_taxonomy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;

class TaxonomyRecordReferencedContentForm extends FormBase {

  protected $records;
  protected $taxonomy_term;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_taxonomy_record_referenced_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $records = NULL, TermInterface $taxonomy_term = NULL) {
    $this->records = $records;
    $this->taxonomy_term = $taxonomy_term;

    $entityViewBuilder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $sortKey = $form_state->getValue('sort');

    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        'newest' => $this->t('Newest first'),
        'oldest' => $this->t('Oldest first'),

      ],
      '#default' => $sortKey ?? 'newest',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'change',
        'wrapper' => 'results-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];

    $sort = ['field' => 'changed', 'direction' => 'DESC'];
    if ($sortKey == 'oldest') {
      $sort = ['field' => 'changed', 'direction' => 'ASC'];
    }

    $form['results'] = [
      '#prefix' => '<div id="results-wrapper">',
      '#suffix' => '</div>',
      '#markup' => '',
    ];

    $results = $this->referencedContent($records, $taxonomy_term, $sort);
    $entities = !empty($results) ? \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($results) : [];
    if (!empty($entities)) {
      foreach ($entities as $entity) {
        $form['results']['items'][] = $entityViewBuilder->view($entity, 'teaser', $langcode);
      }
      $form['results']['items'][] = [
        '#type' => 'pager',
      ];
    }

    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $entityViewBuilder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Determine sort order.
    $sortKey = $form_state->getValue('sort');
    $sort = ['field' => 'changed', 'direction' => 'DESC'];
    if ($sortKey == 'oldest') {
      $sort = ['field' => 'changed', 'direction' => 'ASC'];
    }

    // Refresh the query.
    $results = $this->referencedContent($this->records, $this->taxonomy_term, $sort);
    $entities = !empty($results) ? \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($results) : [];

    // Clear the old results.
    $form['results']['items'] = [];

    // Render the new results.
    if (!empty($entities)) {
      foreach ($entities as $entity) {
        $form['results']['items'][] = $entityViewBuilder->view($entity, 'teaser', $langcode);
      }
      $form['results']['items'][] = [
        '#type' => 'pager',
      ];
    }
    return $form['results'];
  }

  public function referencedContent($records, TermInterface $taxonomy_term, $sort = ['field' => 'changed', 'direction' => 'DESC']) {
    // Get all field definitions for nodes.
    $fields = \Drupal::service('entity_field.manager')->getActiveFieldStorageDefinitions('node');

    // Build a list of all the fields we should be searching.
    $searchFields = [];

    // Entity Reference Fields.
    foreach ($fields as $fieldname => $field) {
      if ($field->gettype() == 'entity_reference') {
        // Find all entity reference field references to this taxonomy term.
        if ($field->getSetting('target_type') == 'taxonomy_term') {
          $searchFields[] = ['fieldname' => $fieldname, 'value' => $taxonomy_term->id(), 'operator' => NULL];
        }
        // Find all entity reference field references to any associated taxonomy records.
        if ($field->getSetting('target_type') == 'node') {
          foreach ($records as $record) {
            $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->id(), 'operator' => NULL];
          }
        }
      }
    }

    // Text Fields that support embeds. Search for embeds of the record(s).
    if (count($records) > 0) {
      foreach ($fields as $fieldname => $field) {
        if (in_array($field->gettype(), ['text', 'text_long', 'text_with_summary'])) {
          foreach ($records as $record) {
            $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->uuid(), 'operator' => 'CONTAINS'];
          }
        }
      }
    }

    // Return early if there are no supported fields to query.
    if (count($searchFields) == 0) {
      return [];
    }

    // Query all those fields for references to the given media.
    $contentQuery = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $fieldConditions = count($searchFields) == 1 ? $contentQuery->andConditionGroup() : $contentQuery->orConditionGroup();

    // Add all the field conditions.
    foreach ($searchFields as $fieldCondition) {
      $fieldConditions->condition($fieldCondition['fieldname'], $fieldCondition['value'], $fieldCondition['operator']);
    }
    $contentQuery->condition($fieldConditions);

    // Published only.
    $contentQuery->condition('status', 1, '=');

    // Sort.
    if (is_array($sort)) {
      $contentQuery->sort($sort['field'], $sort['direction']);
    }

    // Page the results.
    $contentQuery->pager(10);

    // Respect access.
    $contentQuery->accessCheck(TRUE);
    return $contentQuery->execute();
  }

}
