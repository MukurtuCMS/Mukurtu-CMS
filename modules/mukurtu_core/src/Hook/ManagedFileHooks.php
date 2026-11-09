<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for file upload fields.
 */
class ManagedFileHooks {

  public function __construct(
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (isset($info['managed_file'])) {
      $info['managed_file']['#process'][] = [static::class, 'attachResetRefusedUpload'];
      $info['managed_file']['#process'][] = [static::class, 'attachFocusFileField'];
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

  /**
   * Loads the command that keeps focus in a file field with the page.
   *
   * @see ajaxRenderAlter()
   */
  public static function attachFocusFileField(array $element): array {
    $element['#attached']['library'][] = 'mukurtu_core/focus-file-field';
    return $element;
  }

  /**
   * Implements hook_ajax_render_alter().
   *
   * Keeps focus in a file field after its Upload or Remove button replaces
   * it. Added here rather than in the field's AJAX callback, because
   * mukurtu_media swaps in its own callbacks on some media forms.
   */
  #[Hook('ajax_render_alter')]
  public function ajaxRenderAlter(array &$commands): void {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$request->query->has('element_parents')) {
      return;
    }
    $trigger = (string) $request->request->get('_triggering_element_name', '');
    if (preg_match('/_(upload|remove)_button$/', $trigger)) {
      $commands[] = ['command' => 'mukurtuFocusFileField'];
    }
  }

}
