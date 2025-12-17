<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Multipage item manager for loading an MPI from a node.
 */
class MultipageItemManager {

  /**
   * Constructs an MultipageItemManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Get the multipage_item entity that contains the node as a page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface|null
   *   The multipage_item entity or NULL if the node is not in a MPI.
   */
  public function getMultipageEntity(NodeInterface $node): ?MultipageItemInterface {
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
      $mpi_id = reset($result);
      $mpi = $this->entityTypeManager->getStorage('multipage_item')->load($mpi_id);
      return $mpi instanceof MultipageItemInterface ? $mpi : NULL;
    }
    return NULL;
  }

}
