<?php

namespace Drupal\geocoder_field\Plugin\Geocoder\Preprocessor;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\file\Entity\File as FileEntity;
use Drupal\geocoder_field\PreprocessorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a geocoder preprocessor plugin for file fields.
 *
 * @GeocoderPreprocessor(
 *   id = "file",
 *   name = "File",
 *   field_types = {
 *     "file",
 *     "image"
 *   }
 * )
 */
class File extends PreprocessorBase {

  use LoggerChannelTrait;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a geocoder file field preprocessor constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The Country Manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CountryManagerInterface $country_manager, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $country_manager);
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('country_manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess() {
    parent::preprocess();

    foreach ($this->field->getValue() as $delta => $value) {
      if ($value['target_id']) {
        $uri = FileEntity::load($value['target_id'])->getFileUri();
        $value['value'] = $this->fileSystem->realpath($uri);
        try {
          $this->field->set($delta, $value);
        }
        catch (\Exception $e) {
          $this->getLogger('geocoder')->error($e->getMessage());
        }
      }
    }

    return $this;
  }

}
