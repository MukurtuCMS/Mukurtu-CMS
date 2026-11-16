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
   *
   * The replaced field gets a new wrapper ID, which only the response knows,
   * so the command takes it from the markup that replaces the field.
   */
  #[Hook('ajax_render_alter')]
  public function ajaxRenderAlter(array &$commands): void {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$request->query->has('element_parents')) {
      return;
    }
    $trigger = (string) $request->request->get('_triggering_element_name', '');
    if (!preg_match('/_(upload|remove)_button$/', $trigger)) {
      return;
    }
    foreach (array_reverse($commands) as $command) {
      // The field's own replacement targets the AJAX wrapper, so it has no
      // selector of its own.
      if (($command['command'] ?? '') === 'insert' && empty($command['selector'])
        && preg_match('/^\s*<div id="([^"]+)"/', (string) ($command['data'] ?? ''), $matches)) {
        $commands[] = [
          'command' => 'mukurtuFocusFileField',
          'selector' => '#' . $matches[1],
        ];
        return;
      }
    }
  }

}
