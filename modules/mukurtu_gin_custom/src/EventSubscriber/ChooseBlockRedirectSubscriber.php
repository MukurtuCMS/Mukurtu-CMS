<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
 * user has to click through an empty page + filter box for nothing. Redirect
 * straight to the "Add a block" link's target when that happens.
 */
class ChooseBlockRedirectSubscriber implements EventSubscriberInterface {

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
   * Redirects to the inline block list when there's nothing else to show.
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

    $url = $build['add_block']['#url'];
    $url->setOption('query', $request->query->all());
    $event->setResponse(new RedirectResponse($url->toString()));
  }

}
