<?php

namespace Drupal\media_entity_soundcloud\Plugin\media\Source;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Soundcloud entity media source.
 *
 * @MediaSource(
 *   id = "soundcloud",
 *   label = @Translation("Soundcloud"),
 *   allowed_field_types = {"string", "string_long", "link"},
 *   default_thumbnail_filename = "soundcloud.png",
 *   description = @Translation("Provides business logic and metadata for Soundcloud."),
 *   forms = {
 *     "media_library_add" = "\Drupal\media_entity_soundcloud\Form\SoundcloudForm"
 *   }
 * )
 */
class Soundcloud extends MediaSourceBase {

  use StringTranslationTrait;

  /**
   * Soundcloud attributes.
   *
   * @var array
   */
  protected $soundcloud;

  /**
   * Config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Http Client Interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, ClientInterface $httpClient) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    $attributes = [
      'track_id' => $this->t('The track id - not always available'),
      'playlist_id' => $this->t('The playlist (set) id - not always available'),
      'source_id' => $this->t('Compound of source type (track or playlist) and id so that it is unique among all SoundCloud media'),
      'html' => $this->t('HTML embed code'),
      'thumbnail_uri' => $this->t('URI of the thumbnail'),
    ];
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $file_system = \Drupal::service('file_system');
    $content_url = $this->getMediaUrl($media);
    if ($content_url === FALSE) {
      return FALSE;
    }

    $data = $this->oEmbed($content_url);
    if ($data === FALSE) {
      return FALSE;
    }

    switch ($attribute_name) {
      case 'html':
        return $data['html'];

      case 'thumbnail_uri':
        if (isset($data['thumbnail_url'])) {
          $destination = $this->configFactory->get('media_entity_soundcloud.settings')->get('thumbnail_destination');
          $local_uri = $destination . '/' . pathinfo($data['thumbnail_url'], PATHINFO_BASENAME);

          // Save the file if it does not exist.
          if (!file_exists($local_uri)) {
            $file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

            $image = file_get_contents($data['thumbnail_url']);
            $file_system->saveData($image, $local_uri, FileSystemInterface::EXISTS_REPLACE);
          }
          return $local_uri;
        }
        return parent::getMetadata($media, $attribute_name);

      case 'track_id':
      case 'playlist_id':
      case 'source_id':
        // Extract the src attribute from the html code.
        preg_match('/src="([^"]+)"/', $data['html'], $src_matches);
        if (!count($src_matches)) {
          return FALSE;
        }

        // Extract the id from the src.
        preg_match('#/(tracks|playlists)/(\d+)#', urldecode($src_matches[1]), $matches);
        if (!count($matches)) {
          return FALSE;
        }

        if ($attribute_name == 'source_id') {
          return $matches[1] . '/' . $matches[2];
        }
        elseif (($attribute_name == 'track_id' && $matches[1] == 'tracks') || ($attribute_name == 'playlist_id' && $matches[1] == 'playlists')) {
          return $matches[2];
        }

        return FALSE;
      case 'secret_token':
        // Extract the src attribute from the html code.
        preg_match('/src="([^"]+)"/', $data['html'], $src_matches);
        if (!count($src_matches)) {
          return FALSE;
        }

        // Extract the secret_token from the src.
        preg_match('#&(secret_token)=([^&]+)#', urldecode($src_matches[1]), $matches);
        if (!count($matches)) {
          return FALSE;
        }

        return $matches[2];

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Returns the track id from the source_url_field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|bool
   *   The track if from the source_url_field if found. False otherwise.
   */
  protected function getMediaUrl(MediaInterface $media) {
    $source_field = $this->getSourceFieldDefinition($media->bundle->entity);
    $field_name = $source_field->getName();
    if ($media->hasField($field_name)) {
      $property_name = $source_field->getFieldStorageDefinition()->getMainPropertyName();
      return $media->{$field_name}->{$property_name};
    }
    return FALSE;
  }

  /**
   * Returns oembed data for a Soundcloud url.
   *
   * @param string $url
   *   The Soundcloud Url.
   *
   * @return array
   *   An array of oembed data.
   */
  protected function oEmbed($url) {
    $this->soundcloud = &drupal_static(__FUNCTION__ . hash('md5', $url));

    if (!isset($this->soundcloud)) {
      $url = 'https://soundcloud.com/oembed?format=json&url=' . $url;
      try {
        $response = $this->httpClient->get($url);
        $this->soundcloud = Json::decode((string) $response->getBody());
      }
      catch (ClientException $e) {
        $this->soundcloud = FALSE;
      }
    }

    return $this->soundcloud;
  }

}
