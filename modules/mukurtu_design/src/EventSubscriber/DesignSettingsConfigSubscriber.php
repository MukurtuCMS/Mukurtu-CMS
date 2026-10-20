<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\mukurtu_design\DesignPalette;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the custom palette stylesheet in step with its config.
 *
 * The stylesheet is a file on disk, and DesignPalette::alterThemeLibraries()
 * only writes it when it is missing. Regenerating on every save of the
 * settings, rather than only from the settings form, covers config import,
 * `drush config:set` and recipes too. See issue #2181.
 */
class DesignSettingsConfigSubscriber implements EventSubscriberInterface {

  public function __construct(protected ClassResolverInterface $classResolver) {}

  /**
   * Regenerates the stylesheet when the custom palette's colours change.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() !== DesignPalette::SETTINGS || $config->get('palette') !== 'custom') {
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
