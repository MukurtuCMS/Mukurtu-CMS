<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;

class MediaFoundInController extends ControllerBase {

  /**
   * Check access for viewing the "found in" entity report.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(MediaInterface $media) {
    return $media->access('update', $this->currentUser(), TRUE);
  }

  /**
   * Find entity reference fields that reference the given media.
   */
  protected function getEntityReferenceFieldReferences(MediaInterface $media, $entity_type_id = 'node') {
    // Get active entity reference fields for nodes that reference media.
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
    $refFields = [];
    foreach ($fields as $fieldname => $field) {
      if ($field->gettype() == 'entity_reference' && $field->getSetting('target_type') == 'media') {
        $refFields[] = $fieldname;
      }
    }

    // Return early if there are no supported fields to query.
    if (empty($refFields)) {
      return [];
    }

    // Query all those fields for references to the given media.
    $conjunction = count($refFields) == 1 ? 'AND' : 'OR';
    $contentQuery = $this->entityTypeManager()->getStorage($entity_type_id)->getQuery($conjunction);
    foreach ($refFields as $refField) {
      $contentQuery->condition($refField, $media->id())->accessCheck(TRUE);
    }
    $contentQuery->pager(10);
    $results = $contentQuery->execute();

    return $results;
  }

  /**
   * Find text fields that have the given media embedded.
   */
  protected function getEmbeddedReferences(MediaInterface $media, $entity_type_id = 'node') {
    // Get text fields for nodes that can embed media.
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
    $refFields = [];
    foreach ($fields as $fieldname => $field) {
      if (in_array($field->gettype(), ['text', 'text_long', 'text_with_summary'])) {
        $refFields[] = $fieldname;
      }
    }

    // Return early if there are no supported fields to query.
    if (empty($refFields)) {
      return [];
    }

    // Query all those fields for references to the given media.
    $conjunction = count($refFields) == 1 ? 'AND' : 'OR';
    $contentQuery = $this->entityTypeManager()->getStorage($entity_type_id)->getQuery($conjunction);
    foreach ($refFields as $refField) {
      $contentQuery->condition($refField, $media->uuid(), 'CONTAINS')->accessCheck(TRUE);
    }
    $contentQuery->pager();
    $results = $contentQuery->execute();

    return $results;
  }

  /**
   * Render the results from field queries.
   */
  protected function renderResults($results, $section_label, $entity_type_id = 'node', $view_mode = 'browse') {
    $build = [];

    // Load the entities so we can render them.
    $entities = !empty($results) ? $this->entityTypeManager()->getStorage($entity_type_id)->loadMultiple($results) : [];

    if (!empty($entities)) {
      $build['reference_fields'] = [
        '#type' => 'details',
        '#title' => $section_label,
        '#open' => TRUE,
      ];
    }

    // Render the entities.
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $entityViewBuilder = $this->entityTypeManager()->getViewBuilder($entity_type_id);
    foreach ($entities as $entity) {
      $build['reference_fields']['results'][] = $entityViewBuilder->view($entity, $view_mode, $langcode);
    }
    $build['reference_fields']['results'][] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Build the "found in" report.
   */
  public function content(MediaInterface $media) {
    $build = [];

    foreach (['community', 'protocol'] as $entityTypeId) {
      $label = $this->entityTypeManager()->getDefinition($entityTypeId)->getLabel();
      $build[] = $this->renderResults($this->getEntityReferenceFieldReferences($media, $entityTypeId), $this->t('@entityTypeLabel Media Fields', ['@entityTypeLabel' => $label]), $entityTypeId);
      $build[] = $this->renderResults($this->getEmbeddedReferences($media, $entityTypeId), $this->t('@entityTypeLabel Text Fields with Media Embedded', ['@entityTypeLabel' => $label]), $entityTypeId);
    }

    $nodeLabel = $this->entityTypeManager()->getDefinition('node')->getLabel();
    $build[] = $this->renderResults($this->getEntityReferenceFieldReferences($media), $this->t('@entityTypeLabel Media Fields', ['@entityTypeLabel' => $nodeLabel]));
    $build[] = $this->renderResults($this->getEmbeddedReferences($media), $this->t('@entityTypeLabel Text Fields with Media Embedded', ['@entityTypeLabel' => $nodeLabel]));

    return $build;
  }

}
