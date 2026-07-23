<?php

namespace Drupal\mukurtu_gin_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Surfaces the "layout saved" confirmation, which has nowhere else to render.
 *
 * The Layout Builder edit page's own template has no "highlighted" region
 * (or any other block region besides "content"), so a status message queued
 * via the core Messenger service -- "The layout override has been saved.",
 * queued by LayoutBuilderEntityFormTrait::saveTasks() on save -- has nowhere
 * to display through the normal block/region system on this page (confirmed
 * by inspecting a real save response: the queued message never appears
 * anywhere in the returned HTML). gin_lb's default save_behavior redirects
 * back to this same page rather than the node's canonical view, so there's
 * no other page for it to naturally show on either.
 *
 * mukurtu-gin-custom.js calls this endpoint once per page load and displays
 * whatever it returns via a Toastify call it drives itself, since that's the
 * only reliable way to surface a message on this page at all.
 *
 * The "You have unsaved changes." warning is deliberately not handled here;
 * see LbSuppressUnsavedWarningSubscriber, which discards it entirely.
 *
 * @see js/lb-pending-messages.js
 */
class LbPendingMessagesController extends ControllerBase {

  /**
   * Returns any queued status messages as plain text, draining them.
   */
  public function build(): JsonResponse {
    $messenger = $this->messenger();

    $statuses = $messenger->messagesByType(MessengerInterface::TYPE_STATUS);
    if ($statuses) {
      $messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    }

    return new JsonResponse([
      'statuses' => array_map('strval', $statuses),
    ]);
  }

}
