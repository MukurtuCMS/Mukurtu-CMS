<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the membership migrations' missing-group handling and its update.
 *
 * @see mukurtu_migrate_update_40213()
 */
#[Group('mukurtu_migrate')]
class MembershipMissingGroupUpdateTest extends KernelTestBase {

  /**
   * The membership migrations and the group migration each looks up.
   */
  const MIGRATIONS = [
    'mukurtu_cms_v3_community_memberships' => [
      'mukurtu_cms_v3_communities',
      'community',
    ],
    'mukurtu_cms_v3_language_community_community_memberships' => [
      'mukurtu_cms_v3_language_communities',
      'language community',
    ],
    'mukurtu_cms_v3_protocol_memberships' => [
      'mukurtu_cms_v3_cultural_protocols',
      'protocol',
    ],
    'mukurtu_cms_v3_language_community_protocol_memberships' => [
      'mukurtu_cms_v3_language_communities',
      'language community',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';
  }

  /**
   * Returns the shipped config for a migration.
   */
  protected function shippedMigration(string $id): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    return Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . $id . '.yml'));
  }

  /**
   * Saves a migration's config as it shipped before the update.
   */
  protected function saveOldMigration(string $id): void {
    $data = $this->shippedMigration($id);
    if (isset(static::MIGRATIONS[$id])) {
      $group_migration = static::MIGRATIONS[$id][0];
      $data['process']['entity_id'] = [
        'plugin' => 'migration_lookup',
        'migration' => [$group_migration],
        'source' => 'gid',
      ];
      $data['migration_dependencies']['required'] = array_values(array_diff($data['migration_dependencies']['required'], ['mukurtu_cms_v3_cultural_protocols']));
    }
    else {
      $data['process']['field_coverage/geo_type'] = [
        [
          'plugin' => 'callback',
          'source' => 'field_coverage/0/geo_type',
          'callable' => 'ucfirst',
        ],
      ];
    }
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
  }

  /**
   * Returns a migration's saved config.
   */
  protected function savedMigration(string $id): array {
    return \Drupal::config('migrate_plus.migration.' . $id)->getRawData();
  }

  /**
   * Provides the membership migration ids.
   */
  public static function membershipMigrationProvider(): array {
    $cases = [];
    foreach (static::MIGRATIONS as $id => [, $group]) {
      $cases[$id] = [$id, $group];
    }
    return $cases;
  }

  /**
   * Tests that a membership whose group wasn't migrated is skipped.
   */
  #[DataProvider('membershipMigrationProvider')]
  public function testMissingGroupSkipsMembership(string $id, string $group): void {
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($this->shippedMigration($id));
    $row = new Row(['gid' => 1735]);

    try {
      (new MigrateExecutable($migration))->processRow($row, ['entity_id' => $migration->getProcess()['entity_id']]);
      $this->fail('The membership was not skipped.');
    }
    catch (MigrateSkipRowException $e) {
      $this->assertSame("Membership not created: source $group could not be matched to a migrated $group.", $e->getMessage());
    }
  }

  /**
   * Tests that the update applies the shipped config to unmodified migrations.
   */
  public function testUpdateReplacesShippedConfig(): void {
    foreach ([...array_keys(static::MIGRATIONS), 'mukurtu_cms_v3_digital_heritage'] as $id) {
      $this->saveOldMigration($id);
    }

    $this->assertNotNull(mukurtu_migrate_update_40213());

    foreach (array_keys(static::MIGRATIONS) as $id) {
      $saved = $this->savedMigration($id);
      $shipped = $this->shippedMigration($id);
      $this->assertSame($shipped['process']['entity_id'], $saved['process']['entity_id'], $id);
      $this->assertSame($shipped['migration_dependencies'], $saved['migration_dependencies'], $id);
    }
    $this->assertSame(
      $this->shippedMigration('mukurtu_cms_v3_digital_heritage')['process']['field_coverage/geo_type'],
      $this->savedMigration('mukurtu_cms_v3_digital_heritage')['process']['field_coverage/geo_type'],
    );

    // Running it again changes nothing.
    $this->assertNull(mukurtu_migrate_update_40213());
  }

  /**
   * Tests that the update leaves a customized lookup alone.
   */
  public function testUpdateKeepsCustomizedLookup(): void {
    $this->saveOldMigration('mukurtu_cms_v3_community_memberships');
    $custom = [
      'plugin' => 'migration_lookup',
      'migration' => ['my_communities'],
      'source' => 'gid',
    ];
    \Drupal::configFactory()->getEditable('migrate_plus.migration.mukurtu_cms_v3_community_memberships')
      ->set('process.entity_id', $custom)
      ->save();

    $this->assertNull(mukurtu_migrate_update_40213());
    $this->assertSame($custom, $this->savedMigration('mukurtu_cms_v3_community_memberships')['process']['entity_id']);
  }

  /**
   * Tests that the update does nothing on a site without the migrations.
   */
  public function testUpdateWithoutMigrations(): void {
    $this->assertNull(mukurtu_migrate_update_40213());
  }

}
