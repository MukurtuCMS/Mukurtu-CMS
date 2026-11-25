<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\mukurtu_design\CustomCss;
use Drupal\mukurtu_design\DesignPalette;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the generated stylesheets in step with their config.
 *
 * The custom palette and the custom CSS are each a file on disk, and the
 * library alters only write them when they are missing. Regenerating on every
 * save of the
 * settings, rather than only from the settings form, covers config import,
 * `drush config:set` and recipes too. See issue #2181.
 */
class DesignSettingsConfigSubscriber implements EventSubscriberInterface {

  public function __construct(protected ClassResolverInterface $classResolver) {}

  /**
   * Regenerates the stylesheets when the custom CSS or palette changes.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() !== DesignPalette::SETTINGS) {
      return;
    }

    if ($event->isChanged(CustomCss::KEY)) {
      $this->classResolver->getInstanceFromDefinition(CustomCss::class)
        ->write((string) $config->get(CustomCss::KEY));
      // The stylesheet's URL is part of the cached library definitions.
      Cache::invalidateTags(['library_info']);
    }

    if ($config->get('palette') !== 'custom') {
      return;
    }
    if (!$event->isChanged('palette') && !$event->isChanged('colors')) {
      return;
    }

    $this->classResolver->getInstanceFromDefinition(DesignPalette::class)
      ->generateCustomCss($config->get('colors') ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

}
