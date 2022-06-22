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
   * Find content entity reference fields that reference the given media.
   */
  protected function getEntityReferenceFieldReferences(MediaInterface $media, $view_mode = 'browse') {
    // Get active entity reference fields for nodes that reference media.
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions('node');
    $refFields = [];
    foreach ($fields as $fieldname => $field) {
      if ($field->gettype() == 'entity_reference' && $field->getSetting('target_type') == 'media') {
        $refFields[] = $fieldname;
      }
    }

    // Query all those fields for references to the given media.
    $contentQuery = $this->entityTypeManager()->getStorage('node')->getQuery('OR');
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
  protected function getEmbeddedReferences(MediaInterface $media, $view_mode = 'browse') {
    // Get text fields for nodes that can embed media.
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions('node');
    $refFields = [];
    foreach ($fields as $fieldname => $field) {
      if (in_array($field->gettype(), ['text', 'text_long', 'text_with_summary'])) {
        $refFields[] = $fieldname;
      }
    }

    // Query all those fields for references to the given media.
    $contentQuery = $this->entityTypeManager()->getStorage('node')->getQuery('OR');
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
  protected function renderResults($node_ids, $view_mode = 'browse') {
    $build = [];

    // Load the nodes so we can render them.
    $nodes = !empty($node_ids) ? $this->entityTypeManager()->getStorage('node')->loadMultiple($node_ids) : [];

    if (!empty($nodes)) {
      $build['reference_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Media Fields'),
        '#open' => TRUE,
      ];
    }

    // Render the nodes.
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $nodeViewBuilder = $this->entityTypeManager()->getViewBuilder('node');
    foreach ($nodes as $node) {
      $build['reference_fields']['results'][] = $nodeViewBuilder->view($node, $view_mode, $langcode);
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

    $build[] = $this->renderResults($this->getEntityReferenceFieldReferences($media), 'browse');
    $build[] = $this->renderResults($this->getEmbeddedReferences($media), 'browse');

    return $build;
  }

}
