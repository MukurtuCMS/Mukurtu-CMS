<?php

namespace Drupal\geolocation\EventSubscriber;

use Drupal\geolocation\Plugin\views\style\CommonMap;
use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only alter views ajax responses.
    if (!($response instanceof ViewAjaxResponse)) {
      return;
    }

    $view = $response->getView();

    if (!is_a($view->getStyle(), CommonMap::class)) {
      // This view is not of maps_common style, but maybe an attachment is.
      $common_map_attachment = FALSE;

      $attached_display_ids = $view->display_handler->getAttachedDisplays();
      foreach ($attached_display_ids as $display_id) {
        $current_display = $view->displayHandlers->get($display_id);
        if (!empty($current_display)) {
          $current_style = $current_display->getPlugin('style');
          if (
            !empty($current_style)
            && is_a($current_style, CommonMap::class)
          ) {
            $common_map_attachment = TRUE;
          }
        }
      }

      if (!$common_map_attachment) {
        return;
      }
    }

    $commands = &$response->getCommands();
    foreach ($commands as $delta => &$command) {
      // Stop the view from scrolling to the top of the page.
      if (
        $command['command'] === 'viewsScrollTop'
        && $event->getRequest()->query->get('page', FALSE) === FALSE
      ) {
        unset($commands[$delta]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

}
