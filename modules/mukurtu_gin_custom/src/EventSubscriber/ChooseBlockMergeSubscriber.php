<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Shows the "Add a block" list inside the "Choose a block" dialog.
 *
 * Core splits Layout Builder's block chooser in two. "Choose a block"
 * (route layout_builder.choose_block) lists blocks to place, and core
 * deliberately excludes inline blocks from it -- see
 * layout_builder_plugin_filter_block_alter(). New content blocks are behind
 * an "Add a block" button that opens a second list
 * (layout_builder.choose_inline_block). With Mukurtu's restrictions, the
 * first list holds only a few views and Mukurtu blocks, so most of what
 * editors want was hidden on the second page.
 *
 * Merge them: add the second list as a "Content blocks" group at the top of
 * the first and hide the button. The links come from getBlockLinks(), so the
 * "Filter by block name" box covers them too.
 *
 * The list is built by whatever controller the choose_inline_block route
 * uses rather than core's directly, because Layout Builder Restrictions
 * swaps in its own controller there to apply the landing page allowlist.
 *
 * Render the list in place rather than redirecting to it. An earlier version
 * of this subscriber used a RedirectResponse, but an HTTP redirect
 * mid-AJAX-request (the off-canvas dialog only ever reaches this route via
 * AJAX) leaves Drupal's per-request asset-library tracking inconsistent.
 * The off-canvas response then loaded core/misc/dialog/dialog.js twice,
 * crashing on "Identifier 'DrupalDialogEvent' has already been declared"
 * and breaking the ajaxSubmit binding needed to save the block.
 */
class ChooseBlockMergeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected RouteProviderInterface $routeProvider,
    protected ControllerResolverInterface $controllerResolver,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

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
   * Adds the inline block list to "Choose a block" and hides the button.
   */
  public function onView(ViewEvent $event): void {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'layout_builder.choose_block') {
      return;
    }

    // Core only adds the button when inline blocks are allowed here, and
    // hides it from users who cannot create content blocks. Follow both.
    $build = $event->getControllerResult();
    if (!is_array($build) || empty($build['add_block']['#access'])) {
      return;
    }

    $controller = $this->routeProvider
      ->getRouteByName('layout_builder.choose_inline_block')
      ->getDefault('_controller');
    $inline_build = call_user_func(
      $this->controllerResolver->getControllerFromDefinition($controller),
      $request->attributes->get('section_storage'),
      (int) $request->attributes->get('delta'),
      $request->attributes->get('region'),
    );
    $links = $inline_build['links'] ?? NULL;
    if (empty($links['#links'])) {
      return;
    }

    // Drop the classes inlineBlockList() adds for its own page, so the group
    // looks like the other categories.
    $links['#attributes']['class'] = array_values(array_diff($links['#attributes']['class'] ?? [], ['inline-block-list']));
    foreach ($links['#links'] as &$link) {
      $link['attributes']['class'] = array_values(array_diff($link['attributes']['class'] ?? [], ['inline-block-list__item']));
    }
    unset($link);

    // Put the group first: it is what editors reach for most. Keep the
    // container's own #properties ahead of it.
    $categories = $build['block_categories'] ?? [];
    $properties = array_filter($categories, static fn($key) => str_starts_with((string) $key, '#'), ARRAY_FILTER_USE_KEY);
    $build['block_categories'] = $properties + [
      'mukurtu_content_blocks' => [
        '#type' => 'details',
        '#attributes' => ['class' => ['js-layout-builder-category']],
        '#open' => TRUE,
        '#title' => $this->t('Content blocks'),
        'links' => $links,
      ],
    ] + array_diff_key($categories, $properties);

    $build['add_block']['#access'] = FALSE;
    $event->setControllerResult($build);
  }

}
