<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for file upload fields.
 */
class ManagedFileHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (isset($info['managed_file'])) {
      $info['managed_file']['#process'][] = [static::class, 'attachResetRefusedUpload'];
    }
  }

  /**
   * Loads the command that clears a refused upload with the page.
   *
   * It can't be attached to the response that uses it. See
   * \Drupal\mukurtu_core\EventSubscriber\UploadTooLargeSubscriber::onException().
   */
  public static function attachResetRefusedUpload(array $element): array {
    $element['#attached']['library'][] = 'mukurtu_core/reset-refused-upload';
    return $element;
  }

}
