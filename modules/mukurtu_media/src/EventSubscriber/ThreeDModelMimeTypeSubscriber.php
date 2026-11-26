<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\EventSubscriber;

use Drupal\Core\File\Event\MimeTypeMapLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds MIME types for 3D model files, which core doesn't know.
 *
 * Without them, uploaded models are stored and served as
 * application/octet-stream. Apple's AR Quick Look refuses a USDZ file served
 * that way.
 */
final class ThreeDModelMimeTypeSubscriber implements EventSubscriberInterface {

  /**
   * MIME types keyed by file extension.
   */
  public const MIME_TYPES = [
    'glb' => 'model/gltf-binary',
    'usdz' => 'model/vnd.usdz+zip',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [MimeTypeMapLoadedEvent::class => 'addMimeTypes'];
  }

  /**
   * Adds the 3D model MIME types to the map.
   */
  public function addMimeTypes(MimeTypeMapLoadedEvent $event): void {
    foreach (self::MIME_TYPES as $extension => $mime_type) {
      $event->map->addMapping($mime_type, $extension);
    }
  }

}
