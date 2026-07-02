<?php

namespace Drupal\mukurtu_core;

/**
 * Shared relabeling for core's account cancellation method options.
 */
final class UserCancelMethods {

  /**
   * Relabels the "Disable the account" cancel method options.
   *
   * Replaces "Disable the account" with "Block the account" in cancel
   * method radio option descriptions wherever they appear in a form
   * subtree.
   */
  public static function relabelCancelMethods(array &$element): void {
    $replacements = [
      "user_cancel_block" => t(
        "Block the user account(s), do not change their content.",
      ),
      "user_cancel_block_unpublish" => t(
        "Block the user account(s) and archive their content.",
      ),
      "user_cancel_reassign" => t(
        "Delete the user account(s), keep their content and assign it to the Anonymous user account. This cannot be undone.",
      ),
      "user_cancel_delete" => t(
        "Delete the user account(s) and their content. This cannot be undone and is high risk.",
      ),
    ];
    foreach ($replacements as $key => $label) {
      if (isset($element["user_cancel_method"]["#options"][$key])) {
        $element["user_cancel_method"]["#options"][$key] = $label;
      }
    }
  }

}
