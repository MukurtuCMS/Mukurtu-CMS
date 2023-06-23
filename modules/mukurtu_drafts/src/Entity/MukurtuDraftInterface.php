<?php

namespace Drupal\mukurtu_drafts\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for access to an entity's draft state.
 *
 * @ingroup entity_type_characteristics
 */
interface MukurtuDraftInterface
{

  /**
   * Returns whether or not the entity is a draft.
   *
   * @return bool
   *   TRUE if the entity is a draft, FALSE otherwise.
   */
  public function isDraft();

  /**
   * Sets the entity as a draft.
   *
   * @return $this
   */
  public function setDraft();

  /**
   * Unsets the entity as draft.
   *
   * @return $this
   */
  public function unsetDraft();
}
