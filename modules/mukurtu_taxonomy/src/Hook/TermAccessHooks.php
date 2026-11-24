<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Hides a term's name from anyone who can't see it on content (#2785).
 *
 * @see \Drupal\mukurtu_core\Service\TermVisibility::access()
 */
class TermAccessHooks {

  /**
   * Implements hook_ENTITY_TYPE_access() for taxonomy_term.
   *
   * Core checks 'view' when only a term's name is shown, so this covers names
   * too. The service is looked up here rather than injected, so kernel tests
   * that enable this module without mukurtu_core still build a container.
   */
  #[Hook('taxonomy_term_access')]
  public function access(TermInterface $term, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'view' || !\Drupal::hasService('mukurtu_core.term_visibility')) {
      return AccessResult::neutral();
    }
    return \Drupal::service('mukurtu_core.term_visibility')->access($term, $account);
  }

}
