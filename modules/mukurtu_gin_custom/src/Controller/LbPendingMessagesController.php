<?php

namespace Drupal\mukurtu_gin_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Surfaces queued Layout Builder messages that have nowhere else to render.
 *
 * The Layout Builder edit page's own template has no "highlighted" region
 * (or any other block region besides "content"), so any message queued via
 * the core Messenger service -- "You have unsaved changes." warnings,
 * "The layout override has been saved." status confirmations, etc. -- has
 * nowhere to display through the normal block/region system, regardless of
 * dialogs or AJAX (confirmed by inspecting a real save response: the queued
 * status message never appears anywhere in the returned HTML).
 *
 * mukurtu-gin-custom.js calls this endpoint and displays whatever it
 * returns via a Toastify call it drives itself, since that's the only
 * reliable way to surface a message on this page at all:
 * - once on page load, for status messages (e.g. right after a save, since
 *   gin_lb's default save_behavior redirects back to this same page);
 * - once an off-canvas dialog closes (debounced against multi-step dialog
 *   flows), for warning messages.
 *
 * @see js/lb-pending-messages.js
 */
class LbPendingMessagesController extends ControllerBase {

  /**
   * Returns any queued warning/status messages as plain text, draining them.
   */
  public function build(): JsonResponse {
    $messenger = $this->messenger();

    $warnings = $messenger->messagesByType(MessengerInterface::TYPE_WARNING);
    if ($warnings) {
      $messenger->deleteByType(MessengerInterface::TYPE_WARNING);
    }

    $statuses = $messenger->messagesByType(MessengerInterface::TYPE_STATUS);
    if ($statuses) {
      $messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    }

    return new JsonResponse([
      'warnings' => array_map('strval', $warnings),
      'statuses' => array_map('strval', $statuses),
    ]);
  }

}
