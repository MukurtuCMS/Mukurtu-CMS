<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base class for Mukurtu Media kernel tests.
 */
abstract class MukurtuMediaTestBase extends KernelTestBase {

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
   * The current test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $currentUser;

  /**
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected Community $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');

    $this->installConfig(['filter', 'og', 'system', 'media']);

    // Create a shared source field used by all test media type bundles.
    // Using a single test source field avoids naming conflicts with Mukurtu's
    // own bundle field definitions (field_media_audio_file, field_media_image,
    // etc.) which are provided programmatically via hook_entity_field_storage_info.
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_test_source',
      'type' => 'file',
    ])->save();

    // Create media type bundles so hook_entity_bundle_info_alter in
    // mukurtu_media assigns the correct custom bundle classes.
    // MediaType::save() will automatically create FieldConfig for the source
    // field on each bundle.
    foreach (['audio', 'image', 'document'] as $bundle) {
      MediaType::create([
        'id' => $bundle,
        'label' => ucfirst($bundle),
        'source' => 'file',
        'source_configuration' => ['source_field' => 'field_media_test_source'],
      ])->save();
    }

    // After saving MediaType bundles the entity field manager may still hold a
    // cached version of the media field definitions that was built before the
    // FieldConfig for field_media_test_source was created. Clear it so that
    // subsequent entity operations (preSave, get(), validate()) pick up the
    // newly-registered source field on every bundle.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Vocabularies used by media bundle field definitions.
    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();
    Vocabulary::create(['vid' => 'contributor', 'name' => 'Contributor'])->save();
    Vocabulary::create(['vid' => 'people', 'name' => 'People'])->save();

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Authenticated role.
    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->save();

    // Protocol steward OG role.
    $protocolStewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'apply protocol',
        'administer permissions',
        'approve and deny subscription',
        'manage members',
        'update group',
      ],
    ]);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    $this->container = \Drupal::getContainer();

    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->currentUser = $user;
    $this->container->get('current_user')->setAccount($user);

    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $community->addMember($user);
    $this->community = $community;

    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user, ['protocol_steward']);
    $this->protocol = $protocol;
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
