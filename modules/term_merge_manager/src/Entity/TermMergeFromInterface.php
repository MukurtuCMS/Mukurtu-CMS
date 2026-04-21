<?php

namespace Drupal\term_merge_manager\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Term merge from entities.
 *
 * @ingroup term_merge_manager
 */
interface TermMergeFromInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Term merge from name.
   *
   * @return string
   *   Name of the Term merge from.
   */
  public function getName();

  /**
   * Sets the Term merge from name.
   *
   * @param string $name
   *   The Term merge from name.
   *
   * @return \Drupal\term_merge_manager\Entity\TermMergeFromInterface
   *   The called Term merge from entity.
   */
  public function setName($name);

  /**
   * Gets the Term merge from creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Term merge from.
   */
  public function getCreatedTime();

  /**
   * Sets the Term merge from creation timestamp.
   *
   * @param int $timestamp
   *   The Term merge from creation timestamp.
   *
   * @return \Drupal\term_merge_manager\Entity\TermMergeFromInterface
   *   The called Term merge from entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Term merge from published status indicator.
   *
   * Unpublished Term merge from are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Term merge from is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Term merge from.
   *
   * @param bool $published
   *   TRUE to set this Term merge from to published, FALSE to set
   *   it to unpublished.
   *
   * @return \Drupal\term_merge_manager\Entity\TermMergeFromInterface
   *   The called Term merge from entity.
   */
  public function setPublished($published);

}
