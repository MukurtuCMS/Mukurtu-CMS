<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40002().
 *
 * The update hook re-imports the mukurtu_cms_v3_media_audio migration config
 * from the module's shipped YAML, so that sites which installed this module
 * before the SoundCloud provider bundle switch was added pick up the new
 * process pipeline (the `bundle` process key and `field_media_soundcloud`)
 * without requiring a full config re-export.
 *
 * @see mukurtu_migrate_update_40002()
 * @see modules/mukurtu_migrate/config/install/migrate_plus.migration.mukurtu_cms_v3_media_audio.yml
 */
#[Group('mukurtu_migrate')]
class MukurtuMigrateUpdate40002Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * migrate_plus's schema doesn't type the migration config's `include` key.
   *
   * Both the old and new fixture YAML ship `include: null`, which is a
   * pre-existing gap in migrate_plus's own config schema (unrelated to this
   * update hook), but only surfaces when config is written via the raw
   * config API (as mukurtu_migrate_update_40002() does) rather than through
   * the config entity API, which silently drops undeclared properties.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The pre-SoundCloud version of the migration config, as YAML.
   *
   * This is the version that shipped after mukurtu_migrate_update_40001()
   * (the author-mapping fix from issue #1403) but before this issue's
   * changes: it has the `uid` process key and the `mukurtu_cms_v3_users`/
   * `mukurtu_cms_v3_users_uid1` dependencies that 40001 adds, but no
   * `bundle` process plugin and no `field_media_soundcloud` process key.
   */
  const OLD_YAML = <<<'YAML'
langcode: en
status: true
dependencies: {  }
id: mukurtu_cms_v3_media_audio
class: null
idMap: {  }
field_plugin_method: null
cck_plugin_method: null
migration_tags:
  - 'Mukurtu 3'
migration_group: mukurtu_cms_v3
label: 'Media - Audio'
source:
  plugin: d7_scald_atom
  atom_type: audio
process:
  id: sid
  uid:
    plugin: migration_lookup
    migration:
      - mukurtu_cms_v3_users
      - mukurtu_cms_v3_users_uid1
    source: publisher
  langcode: language
  name: title
  field_media_audio_file:
    plugin: migration_lookup
    migration:
      - mukurtu_cms_v3_file_private
      - mukurtu_cms_v3_file
    source: base_id
destination:
  plugin: 'entity:media'
  default_bundle: audio
migration_dependencies:
  required:
    - mukurtu_cms_v3_cultural_protocols
    - mukurtu_cms_v3_terms_contributor
    - mukurtu_cms_v3_terms_people
    - mukurtu_cms_v3_terms_media_tags
    - mukurtu_cms_v3_terms_authors
    - mukurtu_cms_v3_file_private
    - mukurtu_cms_v3_file
    - mukurtu_cms_v3_users
    - mukurtu_cms_v3_users_uid1
  optional:
    - d7_field_instance
include: null
YAML;

  /**
   * Tests that the update hook overwrites an old config with the new one.
   */
  public function testUpdate40002OverwritesOldConfig(): void {
    // Simulate a site that installed mukurtu_migrate before the SoundCloud
    // bundle switch was added: save the old process pipeline as the live
    // config entity.
    $old_data = Yaml::decode(static::OLD_YAML);
    \Drupal::entityTypeManager()->getStorage('migration')->create($old_data)->save();

    // Sanity-check the starting state, so a failure later can't be confused
    // with the fixture itself already containing the new keys.
    $before = \Drupal::config('migrate_plus.migration.mukurtu_cms_v3_media_audio')->get('process');
    $this->assertArrayNotHasKey('bundle', $before);
    $this->assertArrayNotHasKey('field_media_soundcloud', $before);

    // Run the update hook under test.
    \Drupal::moduleHandler()->loadInclude('mukurtu_migrate', 'install');
    mukurtu_migrate_update_40002();

    // Reload the config (update hooks operate on config storage directly, so
    // the immutable config object must be re-fetched, not reused).
    $after = \Drupal::config('migrate_plus.migration.mukurtu_cms_v3_media_audio')->get('process');
    $this->assertArrayHasKey('bundle', $after, 'Update hook did not add the bundle process key.');
    $this->assertArrayHasKey('field_media_soundcloud', $after, 'Update hook did not add the field_media_soundcloud process key.');

    // Confirm the resulting config matches the module's shipped YAML
    // exactly, not just a partial/merged overlay of the two versions.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $shipped_file = $module_path . '/config/install/migrate_plus.migration.mukurtu_cms_v3_media_audio.yml';
    $shipped_data = Yaml::decode(file_get_contents($shipped_file));
    $this->assertEquals($shipped_data['process'], $after);
  }

}
