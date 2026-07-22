<?php

namespace Drupal\mukurtu_gin_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Surfaces queued Layout Builder warning messages once a dialog closes.
 *
 * Layout Builder's off-canvas dialogs no longer have
 * "You have unsaved changes." injected into them directly (see issue #1822
 * follow-up review). This endpoint hands the raw, still-queued warning text
 * to mukurtu-gin-custom.js, which displays it via a Toastify call it drives
 * itself -- deliberately bypassing gin_lb's own automatic Toastify behavior
 * (triggered by its '.glb-messages--warning' template class), since that
 * hardcodes a 6-second auto-hide with no per-message override. Driving
 * Toastify directly lets the warning stay on screen (duration: -1) until a
 * user doing a long editing session manually dismisses it, and keeps this
 * endpoint from touching status_messages/[data-drupal-messages] rendering
 * at all, so it can't interact with unrelated queued messages there.
 *
 * @see js/lb-pending-messages.js
 */
class LbPendingMessagesController extends ControllerBase {

  /**
   * Returns any queued warning messages as plain text, draining the queue.
   */
  public function build(): JsonResponse {
    $warnings = $this->messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    if ($warnings) {
      $this->messenger()->deleteByType(MessengerInterface::TYPE_WARNING);
    }

    return new JsonResponse([
      'messages' => array_map('strval', $warnings),
    ]);
  }

}
