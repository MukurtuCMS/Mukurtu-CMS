<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Decides who can import new paragraphs.
 *
 * The paragraphs module allows anyone to create a paragraph that has no
 * parent yet, and an imported paragraph has none until a later row
 * references it. In Mukurtu, paragraphs only belong on content items, so
 * importing them needs the right to create at least one type of content
 * (#1449).
 */
final class ParagraphImportAccess {

  /**
   * Checks whether an account can create any type of content.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The bundle info service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return bool
   *   TRUE if the account can create at least one content type.
   */
  public static function canCreateHost(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info, AccountInterface $account): bool {
    $handler = $entity_type_manager->getAccessControlHandler('node');
    foreach (array_keys($bundle_info->getBundleInfo('node')) as $bundle) {
      if ($handler->createAccess($bundle, $account)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
