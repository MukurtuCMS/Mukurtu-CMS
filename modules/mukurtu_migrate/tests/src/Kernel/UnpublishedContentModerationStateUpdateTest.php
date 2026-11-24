<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that unpublished Mukurtu 3 content migrates as a draft.
 *
 * @see mukurtu_migrate_update_40214()
 */
#[Group('mukurtu_migrate')]
class UnpublishedContentModerationStateUpdateTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  /**
   * The node migrations whose content types use a moderation workflow.
   */
  const MIGRATIONS = [
    'mukurtu_cms_v3_article',
    'mukurtu_cms_v3_collection',
    'mukurtu_cms_v3_dictionary_word',
    'mukurtu_cms_v3_digital_heritage',
    'mukurtu_cms_v3_page',
    'mukurtu_cms_v3_person',
    'mukurtu_cms_v3_word_list',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'workflows',
    'content_moderation',
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
    unset($data['process']['moderation_state']);
    if ($id === 'mukurtu_cms_v3_dictionary_word') {
      unset($data['process']['status']);
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
   * Provides the migration ids with each source status.
   */
  public static function statusProvider(): array {
    $cases = [];
    foreach (static::MIGRATIONS as $id) {
      // The source database returns status as a string.
      $cases["$id unpublished"] = [$id, '0', 'draft'];
      $cases["$id published"] = [$id, '1', 'published'];
    }
    return $cases;
  }

  /**
   * Tests that each migration maps the source status to a moderation state.
   */
  #[DataProvider('statusProvider')]
  public function testStatusMapsToModerationState(string $id, string $status, string $expected): void {
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($this->shippedMigration($id));
    $process = $migration->getProcess();
    $row = new Row(['status' => $status]);

    (new MigrateExecutable($migration))->processRow($row, [
      'status' => $process['status'],
      'moderation_state' => $process['moderation_state'],
    ]);

    $this->assertSame($status, $row->getDestinationProperty('status'));
    $this->assertSame($expected, $row->getDestinationProperty('moderation_state'));
  }

  /**
   * Tests that a draft moderation state keeps a migrated node unpublished.
   *
   * Without a moderation state, the workflow's default of "published"
   * overrides the migrated status.
   */
  public function testModerationStateKeepsNodeUnpublished(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'content_moderation']);
    $this->setUpCurrentUser([], [], TRUE);

    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital heritage'])->save();
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'digital_heritage');
    $workflow->getTypePlugin()->setConfiguration(['default_moderation_state' => 'published'] + $workflow->getTypePlugin()->getConfiguration());
    $workflow->save();

    $before = Node::create(['type' => 'digital_heritage', 'title' => 'Before', 'status' => 0]);
    $before->save();
    $this->assertTrue($before->isPublished());

    $after = Node::create([
      'type' => 'digital_heritage',
      'title' => 'After',
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $after->save();
    $this->assertFalse($after->isPublished());
  }

  /**
   * Tests that the update adds the shipped mapping to unmodified migrations.
   */
  public function testUpdateAddsModerationState(): void {
    foreach (static::MIGRATIONS as $id) {
      $this->saveOldMigration($id);
    }

    $this->assertNotNull(mukurtu_migrate_update_40214());

    foreach (static::MIGRATIONS as $id) {
      $saved = $this->savedMigration($id)['process'];
      $shipped = $this->shippedMigration($id)['process'];
      $this->assertSame($shipped['moderation_state'], $saved['moderation_state'], $id);
      $this->assertSame($shipped['status'], $saved['status'], $id);
    }

    // Running it again changes nothing.
    $this->assertNull(mukurtu_migrate_update_40214());
  }

  /**
   * Tests that the update leaves a customized moderation state alone.
   */
  public function testUpdateKeepsCustomizedModerationState(): void {
    $this->saveOldMigration('mukurtu_cms_v3_digital_heritage');
    $custom = [
      'plugin' => 'default_value',
      'default_value' => 'draft',
    ];
    \Drupal::configFactory()->getEditable('migrate_plus.migration.mukurtu_cms_v3_digital_heritage')
      ->set('process.moderation_state', $custom)
      ->save();

    $this->assertNull(mukurtu_migrate_update_40214());
    $this->assertSame($custom, $this->savedMigration('mukurtu_cms_v3_digital_heritage')['process']['moderation_state']);
  }

  /**
   * Tests that the update does nothing on a site without the migrations.
   */
  public function testUpdateWithoutMigrations(): void {
    $this->assertNull(mukurtu_migrate_update_40214());
  }

}
