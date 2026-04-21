<?php

namespace Drupal\test_feature_generation\Plugin\FeaturesGeneration;

use Drupal\features\Plugin\FeaturesGeneration\FeaturesGenerationArchive;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FeaturesGenerationArchiveAlter.
 */
class FeaturesGenerationArchiveAlter extends FeaturesGenerationArchive {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      'vfs://drupal',
      $container->get('csrf_token'),
      $container->get('file_system')
    );
  }

}
