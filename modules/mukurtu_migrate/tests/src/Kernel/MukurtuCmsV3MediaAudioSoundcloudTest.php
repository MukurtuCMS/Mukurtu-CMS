<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\media\Entity\Media;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the V3 -> V4 audio media migration's provider-based bundle switch.
 *
 * This exercises the mukurtu_cms_v3_media_audio migration (source plugin
 * d7_scald_atom) against a hand-authored fake D7 source database, since no
 * real V3 site with SoundCloud data is available (SoundCloud's V3-era API
 * integration is broken, so new V3 SoundCloud fixtures can't be generated).
 *
 * @see modules/mukurtu_migrate/config/install/migrate_plus.migration.mukurtu_cms_v3_media_audio.yml
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\source\ScaldAtom
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3MediaAudioSoundcloudTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'file',
    'image',
    'taxonomy',
    'media',
    'media_library',
    'views',
    'og',
    'key',
    'media_entity_soundcloud',
    'geofield',
    'leaflet',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_media',
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'mukurtu_migrate',
  ];

  /**
   * The SoundCloud track URL used in the fake source data.
   */
  const SOUNDCLOUD_URL = 'https://soundcloud.com/artist/track-name';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('mukurtu_protocol', ['mukurtu_protocol_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installMediaTypeConfig('audio');
    $this->installMediaTypeConfig('soundcloud');

    $this->createFakeD7SourceDatabase();
    $this->installAudioMigrationConfig();
  }

  /**
   * Installs a single mukurtu_core media_type config entity by bundle ID.
   *
   * installConfig(['mukurtu_core']) is intentionally avoided here: that
   * module's config/install directory also ships unrelated config (e.g.
   * comment.type.comment) that depends on modules ('comment', etc.) this
   * test has no reason to enable. Only the specific media type config
   * entities the audio migration's bundle switch resolves against
   * ('audio', 'soundcloud') are installed directly.
   */
  protected function installMediaTypeConfig(string $bundle): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $file = $module_path . '/config/install/media.type.' . $bundle . '.yml';
    $data = Yaml::decode(file_get_contents($file));
    \Drupal::entityTypeManager()->getStorage('media_type')->create($data)->save();
  }

  /**
   * Builds the minimal fake D7 source tables ScaldAtom::prepareRow() needs.
   */
  protected function createFakeD7SourceDatabase(): void {
    $schema = $this->sourceDatabase->schema();

    // D7 {system} table. DrupalSqlBase::checkRequirements() looks up the
    // 'scald' module here (the d7_scald_atom source plugin's source_module)
    // and throws a RequirementsException if it isn't found and enabled.
    $schema->createTable('system', [
      'fields' => [
        'filename' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'name' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'type' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => ''],
        'owner' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'status' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'bootstrap' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'schema_version' => ['type' => 'int', 'not null' => TRUE, 'default' => -1],
        'weight' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'info' => ['type' => 'text', 'not null' => FALSE],
      ],
      'primary key' => ['filename'],
    ]);
    $this->sourceDatabase->insert('system')->fields([
      'filename' => 'sites/all/modules/scald/scald.module',
      'name' => 'scald',
      'type' => 'module',
      'owner' => '',
      'status' => 1,
      'bootstrap' => 0,
      'schema_version' => 7000,
      'weight' => 0,
      'info' => '',
    ])->execute();

    // D7 {scald_atoms} table, the actual source table for this migration.
    $schema->createTable('scald_atoms', [
      'fields' => [
        'sid' => ['type' => 'int', 'not null' => TRUE],
        'provider' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'type' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'base_id' => ['type' => 'text', 'not null' => FALSE],
        'language' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => ''],
        'publisher' => ['type' => 'int', 'not null' => FALSE],
        'actions' => ['type' => 'text', 'not null' => FALSE],
        'title' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'data' => ['type' => 'text', 'not null' => FALSE],
        'created' => ['type' => 'int', 'not null' => FALSE],
        'changed' => ['type' => 'int', 'not null' => FALSE],
      ],
      'primary key' => ['sid'],
    ]);

    // D7 Field API tables. ScaldAtom::prepareRow() unconditionally queries
    // {field_config_instance}/{field_config} (joined) to discover which CCK
    // fields are attached to the 'audio' Scald atom bundle. Leaving these
    // empty (no field_config_instance rows) means getFields() returns an
    // empty array, so no {field_data_*} tables are needed - every source
    // property referenced further down the process pipeline (field_contributor,
    // field_people, field_scald_protocol, scald_thumbnail, scald_tags) simply
    // resolves to NULL/missing, which core's migration_lookup, sub_process and
    // null_coalesce process plugins all handle gracefully (no exception).
    $schema->createTable('field_config', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'field_name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'translatable' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);
    $schema->createTable('field_config_instance', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'field_id' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'field_name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'entity_type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'bundle' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'deleted' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);

    // Row 1: a SoundCloud atom. base_id holds the full track URL, since
    // unlike Vimeo (a bare numeric ID, see VimeoPlayerUrl), SoundCloud's own
    // oEmbed API (and this project's media_entity_soundcloud source plugin,
    // \Drupal\media_entity_soundcloud\Plugin\media\Source\Soundcloud::oEmbed())
    // requires a full track URL, not a bare ID - there's no known numeric-ID
    // to URL construction possible for SoundCloud the way there is for Vimeo.
    $this->sourceDatabase->insert('scald_atoms')->fields([
      'sid' => 1,
      'provider' => 'scald_soundcloud',
      'type' => 'audio',
      'base_id' => static::SOUNDCLOUD_URL,
      'language' => '',
      'publisher' => 1,
      'actions' => '',
      'title' => 'Test SoundCloud Track',
      'data' => '',
      'created' => 1000000000,
      'changed' => 1000000000,
    ])->execute();

    // Row 2: a plain, locally hosted audio atom (the pre-existing default
    // bundle path). This guards against a regression where introducing the
    // SoundCloud bundle switch breaks native audio atoms.
    $this->sourceDatabase->insert('scald_atoms')->fields([
      'sid' => 2,
      'provider' => 'scald_audio',
      'type' => 'audio',
      'base_id' => '42',
      'language' => '',
      'publisher' => 1,
      'actions' => '',
      'title' => 'Test Native Audio',
      'data' => '',
      'created' => 1000000000,
      'changed' => 1000000000,
    ])->execute();
  }

  /**
   * Installs the real, shipped mukurtu_cms_v3_media_audio migration config.
   *
   * This loads the actual YAML file from mukurtu_migrate's config/install
   * directory (rather than hand-copying its process pipeline into the test),
   * so the test exercises exactly what will ship. Only this one migration
   * config entity is created directly (instead of via installConfig(),
   * which would also install ~47 unrelated sibling migrations and a Views
   * config that isn't needed here).
   */
  protected function installAudioMigrationConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $file = $module_path . '/config/install/migrate_plus.migration.mukurtu_cms_v3_media_audio.yml';
    $data = Yaml::decode(file_get_contents($file));
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
  }

  /**
   * Tests that a scald_soundcloud atom migrates into the soundcloud bundle.
   */
  public function testSoundcloudProviderMapsToSoundcloudBundle(): void {
    $this->startCollectingMessages();
    $this->executeMigration('mukurtu_cms_v3_media_audio');
    $this->assertEmpty($this->migrateMessages['error'] ?? [], print_r($this->migrateMessages['error'] ?? [], TRUE));

    $media = Media::load(1);
    $this->assertNotNull($media, 'Media entity for the SoundCloud atom was not created.');
    $this->assertEquals('soundcloud', $media->bundle());
    $this->assertTrue($media->hasField('field_media_soundcloud'));
    $this->assertEquals(static::SOUNDCLOUD_URL, $media->get('field_media_soundcloud')->value);
  }

  /**
   * Tests that a plain scald_audio atom still migrates into the audio bundle.
   *
   * This is the pre-existing default path; it must be unaffected by adding
   * the SoundCloud branch to the bundle switch.
   */
  public function testNativeAudioProviderMapsToAudioBundle(): void {
    $this->startCollectingMessages();
    $this->executeMigration('mukurtu_cms_v3_media_audio');
    $this->assertEmpty($this->migrateMessages['error'] ?? [], print_r($this->migrateMessages['error'] ?? [], TRUE));

    $media = Media::load(2);
    $this->assertNotNull($media, 'Media entity for the native audio atom was not created.');
    $this->assertEquals('audio', $media->bundle());
    $this->assertFalse($media->hasField('field_media_soundcloud'));
  }

}
