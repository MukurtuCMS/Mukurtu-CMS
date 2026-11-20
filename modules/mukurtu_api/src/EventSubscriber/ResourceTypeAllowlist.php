<?php

declare(strict_types=1);

namespace Drupal\mukurtu_api\EventSubscriber;

use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exposes only content-facing entity types through JSON:API.
 *
 * Core JSON:API exposes every entity type by default, including internal
 * ones such as memberships, messages, import settings and configuration. An
 * allowlist keeps new entity types hidden until someone decides they belong
 * in the API, which is safer than listing what to hide.
 */
final class ResourceTypeAllowlist implements EventSubscriberInterface {

  /**
   * Entity types an app needs.
   */
  const ENTITY_TYPES = [
    'community',
    'file',
    'media',
    'multipage_item',
    'node',
    'paragraph',
    'protocol',
    'taxonomy_term',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // The jsonapi module isn't a dependency, so this event may never fire.
    if (!class_exists(ResourceTypeBuildEvents::class)) {
      return [];
    }
    return [ResourceTypeBuildEvents::BUILD => 'onBuild'];
  }

  /**
   * Disables resource types for entity types outside the allowlist.
   */
  public function onBuild(ResourceTypeBuildEvent $event): void {
    [$entityTypeId] = explode('--', $event->getResourceTypeName(), 2);
    if (!in_array($entityTypeId, self::ENTITY_TYPES, TRUE)) {
      $event->disableResourceType();
    }
  }

}
