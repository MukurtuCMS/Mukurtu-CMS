<?php

declare(strict_types=1);

namespace Drupal\geocoder_field\EventSubscriber;

use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\Event\WorkspacePublishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event subscriber to respond to workspace publishing events.
 *
 * The geocoding operations from geocoder_field_entity_presave() can be very
 * expensive when updating many entities at once. Workspace publishing doesn't
 * change any field data, it only re-saves the latest workspace-specific
 * revision and sets it as the default one, so there is no need to update
 * geocoding data.
 *
 * @see geocoder_field_entity_presave()
 */
class WorkspacePublishingSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new WorkspacePublishingSubscriber.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack object.
   */
  public function __construct(
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(WorkspacePublishEvent::class)) {
      return [];
    }

    return [
      WorkspacePrePublishEvent::class => ['onPrePublish'],
      WorkspacePostPublishEvent::class => ['onPostPublish'],
    ];
  }

  /**
   * Adds a custom request attribute to prevent geocoding updates.
   */
  public function onPrePublish(): void {
    if ($request = $this->requestStack->getCurrentRequest()) {
      $request->attributes->set('geocoder_presave_disabled', TRUE);
    }
  }

  /**
   * Removes the custom request attribute.
   */
  public function onPostPublish(): void {
    if ($request = $this->requestStack->getCurrentRequest()) {
      $request->attributes->remove('geocoder_presave_disabled');
    }
  }

}
