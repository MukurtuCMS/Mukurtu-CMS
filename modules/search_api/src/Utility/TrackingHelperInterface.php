<?php

declare(strict_types=1);

namespace Drupal\search_api\Utility;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for the tracking helper service.
 */
interface TrackingHelperInterface {

  /**
   * Reacts to an entity being updated or deleted.
   *
   * Determines whether this entity is indirectly referenced in any search index
   * and, if so, marks all items referencing it as updated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that just got changed (updated or deleted).
   * @param bool $deleted
   *   (optional) TRUE if the entity was deleted, FALSE if it was updated.
   *
   * @see \Drupal\search_api\Datasource\DatasourceInterface::getAffectedItemsForEntityChange()
   */
  public function trackReferencedEntityUpdate(EntityInterface $entity, bool $deleted = FALSE);

}
