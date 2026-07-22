<?php

namespace Drupal\mukurtu_gin_custom\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Surfaces queued Layout Builder warning messages on the base page.
 *
 * Layout Builder's off-canvas dialogs no longer have
 * "You have unsaved changes." injected into them directly: gin_lb's
 * Toastify presentation renders any status message as a viewport-fixed
 * toast, which visually collides with the off_canvas_top dialog panel
 * regardless of where in the DOM the message markup lives (see issue #1822
 * follow-up review). Instead, mukurtu-gin-custom.js calls this endpoint
 * once the off-canvas dialog closes, so the warning surfaces on the base
 * page -- with no dialog open to collide with -- instead of only leaking to
 * whatever unrelated page loads next.
 *
 * @see js/lb-pending-messages.js
 */
class LbPendingMessagesController extends ControllerBase {

  /**
   * Returns an AJAX command inserting any queued warning messages.
   */
  public function build(): AjaxResponse {
    $response = new AjaxResponse();

    // Peek rather than drain: skip inserting an empty status_messages
    // wrapper when there's nothing queued.
    if (!$this->messenger()->messagesByType(MessengerInterface::TYPE_WARNING)) {
      return $response;
    }

    $build = [
      '#type' => 'status_messages',
      '#display' => 'warning',
    ];
    $response->addCommand(new InsertCommand('[data-drupal-messages]', $build));

    return $response;
  }

}
