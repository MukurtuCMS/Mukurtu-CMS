<?php

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface defining a multipage item entity type.
 */
interface MultipageItemInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the multipage item title.
   *
   * @return string
   *   Title of the multipage item.
   */
  public function getTitle();

  /**
   * Sets the multipage item title.
   *
   * @param string $title
   *   The multipage item title.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface
   *   The called multipage item entity.
   */
  public function setTitle($title);

  /**
   * Gets the multipage item creation timestamp.
   *
   * @return int
   *   Creation timestamp of the multipage item.
   */
  public function getCreatedTime();

  /**
   * Sets the multipage item creation timestamp.
   *
   * @param int $timestamp
   *   The multipage item creation timestamp.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface
   *   The called multipage item entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the multipage item status.
   *
   * @return bool
   *   TRUE if the multipage item is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the multipage item status.
   *
   * @param bool $status
   *   TRUE to enable this multipage item, FALSE to disable.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface
   *   The called multipage item entity.
   */
  public function setStatus($status);

  /**
   * Get the first page of the multipage item.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The first page node.
   */
  public function getFirstPage();//: NodeInterface|null;

  /**
   * Set the first page of the multipage item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to make the first page.
   *
   * @return MultipageItemInterface
   *   The multipage entity.
   */
  public function setFirstPage(NodeInterface $node): MultipageItemInterface;

  /**
   * Append a page to the multipage item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to add.
   *
   * @return MultipageItemInterface
   *   The multipage entity.
   */
  public function addPage(NodeInterface $node): MultipageItemInterface;

  /**
   * Get the pages of the multipage item.
   *
   * @param bool $accessCheck
   *   (optional) TRUE if the results should be filtered for access for the current user.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The page nodes.
   */
  public function getPages($accessCheck = FALSE);

  /**
   * Checks if a node is a page in a multipage item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return bool
   *   TRUE if the node is a page in the multipage item.
   */
  public function hasPage(NodeInterface $node): bool;

}
