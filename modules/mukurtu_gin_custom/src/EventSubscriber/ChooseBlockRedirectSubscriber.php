<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Controller\ChooseBlockController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Skips the empty "Choose a block" page for inline-block-only roles.
 *
 * LayoutBuilderHooks::restrictBlocksForRole() limits non-administrators on
 * basic pages to a curated set of inline blocks. Separately, Drupal core's
 * ChooseBlockController::build() (route layout_builder.choose_block) always
 * excludes inline_block plugins from its own "block_categories" listing for
 * every role -- see layout_builder_plugin_filter_block_alter() -- because
 * inline blocks are only ever meant to be reached via the "Add a block"
 * button, which links to the separate choose_inline_block page.
 *
 * Combined, a role restricted to inline blocks only ends up with a
 * completely empty "Choose a block" page: no other categories (removed by
 * our own restriction) and no inline blocks either (removed by core). The
 * user has to click through an empty page + filter box for nothing.
 *
 * Render the "Add a block" page's own content directly instead of
 * redirecting to it. An earlier version of this fix used a plain
 * RedirectResponse, but issuing an HTTP redirect mid-AJAX-request (the
 * off-canvas dialog only ever reaches this route via AJAX) leaves Drupal's
 * per-request asset-library tracking inconsistent between the redirected-
 * from and redirected-to requests. That caused the off-canvas response to
 * load core/misc/dialog/dialog.js twice, crashing on "Identifier
 * 'DrupalDialogEvent' has already been declared" and, as a side effect,
 * breaking the ajaxSubmit binding needed to save the block. Administrators
 * (who see multiple categories and never hit this redirect) never
 * encountered the crash, which is what pointed at this subscriber.
 * Rendering the same content in-place, in the single original request,
 * avoids the second request/response cycle entirely.
 */
class ChooseBlockRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ClassResolverInterface $classResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after gin_lb's own LayoutBuilderBrowserEventSubscriber (weight 50)
    // so we inspect the same final render array it works with.
    $events[KernelEvents::VIEW][] = ['onView', 40];
    return $events;
  }

  /**
   * Shows the inline block list directly when there's nothing else to show.
   */
  public function onView(ViewEvent $event): void {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'layout_builder.choose_block') {
      return;
    }

    $build = $event->getControllerResult();
    if (!is_array($build) || empty($build['add_block']['#access']) || empty($build['add_block']['#url']) || !($build['add_block']['#url'] instanceof Url)) {
      return;
    }

    // "block_categories" always carries #type/#attributes; an actual
    // category adds further keyed entries alongside them.
    $categories = $build['block_categories'] ?? [];
    $has_categories = (bool) array_filter(
      array_keys($categories),
      static function (string $key): bool {
        return !str_starts_with($key, '#');
      }
    );
    if ($has_categories) {
      return;
    }

    /** @var \Drupal\layout_builder\Controller\ChooseBlockController $controller */
    $controller = $this->classResolver->getInstanceFromDefinition(ChooseBlockController::class);
    $inline_build = $controller->inlineBlockList(
      $request->attributes->get('section_storage'),
      $request->attributes->get('delta'),
      $request->attributes->get('region'),
    );
    $event->setControllerResult($inline_build);
  }

}
