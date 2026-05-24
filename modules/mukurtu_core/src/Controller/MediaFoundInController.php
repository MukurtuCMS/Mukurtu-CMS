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
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
    $refFields = [];
    foreach ($fields as $fieldname => $field) {
      if ($field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'media') {
        $refFields[] = $fieldname;
      }
    }

    if (empty($refFields)) {
      return [];
    }

    $conjunction = count($refFields) == 1 ? 'AND' : 'OR';
    $query = $this->entityTypeManager()->getStorage($entity_type_id)->getQuery($conjunction);
    foreach ($refFields as $refField) {
      $query->condition($refField, $media->id());
    }
    $query->accessCheck(FALSE);

    return $query->execute();
  }

  /**
   * Find text fields that have the given media embedded.
   */
  protected function getEmbeddedReferences(MediaInterface $media, $entity_type_id = 'node') {
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
    $textFields = [];
    foreach ($fields as $fieldname => $field) {
      if (in_array($field->getType(), ['text', 'text_long', 'text_with_summary'])) {
        $textFields[] = $fieldname;
      }
    }

    if (empty($textFields)) {
      return [];
    }

    $conjunction = count($textFields) == 1 ? 'AND' : 'OR';
    $query = $this->entityTypeManager()->getStorage($entity_type_id)->getQuery($conjunction);
    foreach ($textFields as $textField) {
      $query->condition($textField, $media->uuid(), 'CONTAINS');
    }
    $query->accessCheck(FALSE);

    return $query->execute();
  }

  /**
   * Build the "found in" report.
   */
  public function content(MediaInterface $media) {
    $rows = [];

    foreach (['node', 'community', 'protocol'] as $entity_type_id) {
      $results = array_unique(array_merge(
        array_values($this->getEntityReferenceFieldReferences($media, $entity_type_id)),
        array_values($this->getEmbeddedReferences($media, $entity_type_id))
      ));

      if (empty($results)) {
        continue;
      }

      $type_label = $this->entityTypeManager()->getDefinition($entity_type_id)->getLabel();
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
      $entities = array_filter(
        $this->entityTypeManager()->getStorage($entity_type_id)->loadMultiple($results),
        fn($e) => $e->access('view', $this->currentUser())
      );

      foreach ($entities as $entity) {
        $bundle_label = $bundle_info[$entity->bundle()]['label'] ?? $entity->bundle();
        $rows[] = [
          [
            'data' => [
              '#type' => 'link',
              '#title' => $entity->label(),
              '#url' => $entity->toUrl(),
            ],
          ],
          (string) $type_label,
          (string) $bundle_label,
        ];
      }
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('Title'), $this->t('Type'), $this->t('Content Type')],
      '#rows' => $rows,
      '#empty' => $this->t('This media is not used anywhere.'),
    ];
  }

}
