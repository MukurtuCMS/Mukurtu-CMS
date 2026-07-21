<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures Layout Builder off-canvas dialog responses drain the messenger.
 *
 * Drupal core's \Drupal\Core\Render\MainContent\DialogRenderer (and its
 * subclass OffCanvasRenderer) -- the renderers used for every Layout Builder
 * off-canvas dialog request (opening "Add a block", navigating a block
 * category picker, loading a configure-section/-block form, etc.) -- never
 * build or render a ['#type' => 'status_messages'] element, unlike
 * \Drupal\Core\Render\MainContent\AjaxRenderer (used for plain, non-dialog
 * AJAX responses), which always does. Because of this, a warning queued in
 * the messenger -- most notably PrepareLayout::onPrepareLayout()'s "You have
 * unsaved changes." warning -- is never drained/displayed during an
 * off-canvas dialog exchange. It sits queued in the session until whatever
 * unrelated full page happens to load next (see issue #1822).
 *
 * The AJAX form-submission (save) path doesn't have this problem and is left
 * untouched: it bypasses this event entirely (FormBuilder throws a
 * FormAjaxException, handled on KernelEvents::EXCEPTION) and every Layout
 * Builder off-canvas form's successful submit handler rebuilds the canvas via
 * LayoutRebuildTrait, which re-dispatches PrepareLayoutEvent and embeds
 * status_messages in that same response.
 *
 * This subscriber covers the GET/navigation side of the off-canvas exchange:
 * it injects the same ['#type' => 'status_messages'] element core's own
 * \Drupal\Core\Ajax\AjaxFormHelperTrait::ajaxSubmit() already uses on its
 * validation-error path, so whatever is queued gets drained and displayed
 * right there in the dialog instead of leaking to a later, unrelated page.
 */
class LbOffCanvasMessagesSubscriber implements EventSubscriberInterface {

  /**
   * Wrapper formats used by Layout Builder's off-canvas dialogs.
   */
  protected const DIALOG_WRAPPER_FORMATS = [
    'drupal_dialog',
    'drupal_dialog.off_canvas',
    'drupal_dialog.off_canvas_top',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after gin_lb's own LayoutBuilderBrowserEventSubscriber (weight 50)
    // and ChooseBlockRedirectSubscriber (priority 40) so this operates on the
    // final render array.
    $events[KernelEvents::VIEW][] = ['onView', 30];
    return $events;
  }

  /**
   * Injects a status_messages element into Layout Builder dialog responses.
   */
  public function onView(ViewEvent $event): void {
    $request = $event->getRequest();

    $route_name = (string) $request->attributes->get('_route');
    if (!str_starts_with($route_name, 'layout_builder.')) {
      return;
    }

    $wrapper_format = $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT);
    if (!in_array($wrapper_format, self::DIALOG_WRAPPER_FORMATS, TRUE)) {
      return;
    }

    $build = $event->getControllerResult();
    if (!is_array($build) || isset($build['status_messages'])) {
      return;
    }

    $build['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];
    $build['#sorted'] = FALSE;

    $event->setControllerResult($build);
  }

}
