<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base class for Mukurtu Media kernel tests.
 */
abstract class MukurtuMediaTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'field',
    'file',
    'filter',
    'flag',
    'image',
    'media',
    'media_entity_soundcloud',
    'node',
    'og',
    'options',
    'path',
    'system',
    'tagify',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
    'mukurtu_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['media']);

    // Register the shared test source field BEFORE installEntitySchema('media')
    // so the field is known when Drupal builds the media entity field map.
    // Using a single shared field avoids naming conflicts with Mukurtu's own
    // bundle field definitions (field_media_audio_file, field_media_image,
    // etc.) which are provided programmatically via hook_entity_field_storage_info.
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_test_source',
      'type' => 'file',
    ])->save();

    // Install media entity schema after the source FieldStorageConfig exists,
    // so the field definition is present in the initial field map build.
    $this->installEntitySchema('media');

    // Create media type bundles so hook_entity_bundle_info_alter in
    // mukurtu_media assigns the correct custom bundle classes.
    foreach (['audio', 'image', 'document'] as $bundle) {
      MediaType::create([
        'id' => $bundle,
        'label' => ucfirst($bundle),
        'source' => 'file',
        'source_configuration' => ['source_field' => 'field_media_test_source'],
      ])->save();

      // Explicitly create the FieldConfig for field_media_test_source on this
      // bundle. hook_media_type_insert should do this automatically, but
      // mukurtu_media's hook_entity_field_storage_info() registers bundle
      // fields as shared storage definitions which can trigger field manager
      // cache rebuilds that lose track of newly-created FieldConfigs. By
      // creating the FieldConfig here we guarantee it exists regardless of
      // hook ordering. If the hook already created it, this is a no-op update.
      if (!FieldConfig::loadByName('media', $bundle, 'field_media_test_source')) {
        FieldConfig::create([
          'field_storage' => FieldStorageConfig::loadByName('media', 'field_media_test_source'),
          'bundle' => $bundle,
          'label' => 'Test Source',
          'required' => FALSE,
        ])->save();
      }
    }

    // Clear the entity field manager cache so that getFieldDefinitions() on
    // any subsequently-created media entity sees the FieldConfig for
    // field_media_test_source on each bundle.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Vocabularies used by media bundle field definitions.
    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();
    Vocabulary::create(['vid' => 'contributor', 'name' => 'Contributor'])->save();
    Vocabulary::create(['vid' => 'people', 'name' => 'People'])->save();
  }

  /**
   * Build an unsaved media entity with protocol configured.
   *
   * @param string $bundle
   *   The media bundle (audio, image, document).
   * @param string $name
   *   The media entity name.
   *
   * @return \Drupal\media\Entity\Media
   */
  protected function buildMedia(string $bundle, string $name): Media {
    /** @var \Drupal\mukurtu_media\Entity\Audio|\Drupal\mukurtu_media\Entity\Image|\Drupal\mukurtu_media\Entity\Document $media */
    $media = Media::create([
      'bundle' => $bundle,
      'name' => $name,
      'uid' => $this->currentUser->id(),
    ]);
    $media->setSharingSetting('any');
    $media->setProtocols([$this->protocol]);
    return $media;
  }

}
