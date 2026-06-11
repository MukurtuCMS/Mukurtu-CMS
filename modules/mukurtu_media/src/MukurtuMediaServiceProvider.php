<?php

namespace Drupal\mukurtu_media;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps the core media library UI builder class.
 *
 * Using ServiceProviderBase::alter() inherits the full service definition
 * (arguments, tags) from core automatically, so this remains safe if core
 * adds or reorders constructor parameters in a future Drupal update.
 */
class MukurtuMediaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('media_library.ui_builder')) {
      $container->getDefinition('media_library.ui_builder')
        ->setClass(MukurtuMediaLibraryUiBuilder::class);
    }
  }

}
