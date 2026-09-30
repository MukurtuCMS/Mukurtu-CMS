<?php

declare(strict_types=1);

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Seeds a not-yet-overridden layout before a front-end block edit.
 *
 * Front-end edit buttons (see LbFrontendEditUrls) link to the LB update-block
 * form for the node's override, even when the node still renders the display
 * defaults. UpdateBlockForm reads the component straight from the override's
 * sections, which are empty until something copies the defaults in. The LB
 * UI does that copy by dispatching PREPARE_LAYOUT (core's PrepareLayout
 * subscriber), so dispatch the same event here rather than duplicating it.
 */
class LbFrontendEditSeedSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ?LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Lower than RouterListener (32) so the section_storage route parameter
    // has already been converted, and before the controller builds the form.
    $events[KernelEvents::REQUEST][] = ['onRequest', 0];
    return $events;
  }

  /**
   * Copies the default sections into the override's tempstore entry.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$this->layoutTempstoreRepository || $request->attributes->get('_route') !== 'layout_builder.update_block' || !$request->query->get('frontend_edit')) {
      return;
    }
    $section_storage = $request->attributes->get('section_storage');
    if (!$section_storage instanceof OverridesSectionStorageInterface ||
        $section_storage->isOverridden() ||
        $this->layoutTempstoreRepository->has($section_storage)) {
      return;
    }
    $this->eventDispatcher->dispatch(new PrepareLayoutEvent($section_storage), LayoutBuilderEvents::PREPARE_LAYOUT);
  }

}
