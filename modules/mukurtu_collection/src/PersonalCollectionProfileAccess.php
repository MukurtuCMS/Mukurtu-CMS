<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Determines which personal collections a viewer may see on a user's profile.
 */
class PersonalCollectionProfileAccess implements PersonalCollectionProfileAccessInterface {

  /**
   * Constructs a new PersonalCollectionProfileAccess.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function getViewableCollectionIds(AccountInterface $viewer, AccountInterface $profileUser): array {
    $storage = $this->entityTypeManager->getStorage('personal_collection');
    $query = $storage->getQuery()
      ->condition('user_id', $profileUser->id())
      ->condition('status', 1)
      ->accessCheck(TRUE);

    if ((int) $viewer->id() !== (int) $profileUser->id()) {
      $query->condition('field_pc_privacy', 'public');
    }

    $ids = $query->sort('created', 'DESC')->execute();
    return array_values($ids);
  }

}
