<?php

namespace Drupal\term_merge_manager\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Term merge into entities.
 *
 * @ingroup term_merge_manager
 */
interface TermMergeIntoInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Term merge into creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Term merge into.
   */
  public function getCreatedTime();

  /**
   * Sets the Term merge into creation timestamp.
   *
   * @param int $timestamp
   *   The Term merge into creation timestamp.
   *
   * @return \Drupal\term_merge_manager\Entity\TermMergeIntoInterface
   *   The called Term merge into entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Term merge into published status indicator.
   *
   * Unpublished Term merge into are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Term merge into is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Term merge into.
   *
   * @param bool $published
   *   TRUE to set this Term merge into to published, FALSE to set it to
   *   unpublished.
   *
   * @return \Drupal\term_merge_manager\Entity\TermMergeIntoInterface
   *   The called Term merge into entity.
   */
  public function setPublished($published);

}
