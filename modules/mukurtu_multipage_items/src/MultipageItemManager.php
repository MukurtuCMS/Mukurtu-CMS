<?php

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class MultipageItemManager {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an MultipageItemManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the multipage_item entity that contains the node as a page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface|null
   *   The multipage_item entity or NULL if the node is not in a MPI.
   */
  public function getMultipageEntity($node) {
    $query = $this->entityTypeManager->getStorage('multipage_item')->getQuery();

    // CRs cannot be pages. Follow the OR relationship if node is a CR.
    if ($node->hasField('field_mukurtu_original_record')) {
      $records = $node->get('field_mukurtu_original_record')->referencedEntities();
      if (!empty($records)) {
        return $this->getMultipageEntity(reset($records));
      }
    }

    // Check if node is in an MPI directly.
    $result = $query->condition('field_pages', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($result)) {
      $mpiId = reset($result);
      return $this->entityTypeManager->getStorage('multipage_item')->load($mpiId);
    }
    return NULL;
  }

}
