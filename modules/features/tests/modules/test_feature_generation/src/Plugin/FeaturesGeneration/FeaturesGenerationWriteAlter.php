<?php

namespace Drupal\test_feature_generation\Plugin\FeaturesGeneration;

use Drupal\features\Plugin\FeaturesGeneration\FeaturesGenerationWrite;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FeaturesGenerationWriteAlter.
 */
class FeaturesGenerationWriteAlter extends FeaturesGenerationWrite {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      'vfs://drupal',
      $container->get('file_system')
    );
  }

}
