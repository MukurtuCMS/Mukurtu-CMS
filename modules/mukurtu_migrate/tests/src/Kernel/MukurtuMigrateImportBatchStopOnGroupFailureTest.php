<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\mukurtu_migrate\Batch\MukurtuMigrateImportBatch;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a failed group migration stops the run, and failed rows retry.
 *
 * Memberships and nearly all content belong to a community or protocol, so
 * the batch stops when a group migration has failed rows, rather than
 * letting everything that depends on the group fail too.
 *
 * The migrations here import users from inline data. A name given as an
 * array makes the explode step throw, which fails that row.
 */
#[Group('mukurtu_migrate')]
class MukurtuMigrateImportBatchStopOnGroupFailureTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_drupal',
    'migrate_drupal_ui',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * The batch saves pathauto.settings, which has no schema here.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);

    // The listeners are added once per process; this test has a new
    // container, so they must be added to it again.
    $property = new \ReflectionProperty(MukurtuMigrateImportBatch::class, 'listenersAdded');
    $property->setValue(NULL, FALSE);
  }

  /**
   * Creates a migration that imports users from inline rows.
   *
   * @param string $id
   *   The migration id.
   * @param array $rows
   *   Rows with an 'id' and a 'name'.
   */
  protected function createUserMigration(string $id, array $rows): void {
    $migration = Migration::load($id) ?? Migration::create([
      'id' => $id,
      'label' => $id === 'mukurtu_cms_v3_cultural_protocols' ? 'Cultural Protocols' : $id,
      'process' => [
        'name' => [
          ['plugin' => 'explode', 'source' => 'name', 'delimiter' => ','],
          ['plugin' => 'extract', 'index' => [0]],
        ],
      ],
      'destination' => ['plugin' => 'entity:user'],
    ]);
    $migration->set('source', [
      'plugin' => 'embedded_data',
      'data_rows' => $rows,
      'ids' => ['id' => ['type' => 'integer']],
    ]);
    $migration->save();
  }

  /**
   * Runs the batch to completion.
   *
   * @param string[] $ids
   *   The migration ids, in order.
   *
   * @return array
   *   The batch context.
   */
  protected function runBatch(array $ids): array {
    $context = [];
    do {
      MukurtuMigrateImportBatch::run($ids, [
        'source_base_path' => '',
        'source_private_file_path' => '',
      ], $context);
    } while ($context['finished'] < 1);
    return $context;
  }

  /**
   * Whether a user with the given name exists.
   */
  protected function userExists(string $name): bool {
    return (bool) \Drupal::entityQuery('user')->accessCheck(FALSE)->condition('name', $name)->execute();
  }

  /**
   * Tests that a group migration with a failed row stops the run.
   */
  public function testGroupFailureStopsRun(): void {
    $this->createUserMigration('mukurtu_cms_v3_cultural_protocols', [
      ['id' => 1, 'name' => 'protocol_one'],
      ['id' => 2, 'name' => ['broken']],
    ]);
    $this->createUserMigration('test_after_groups', [
      ['id' => 1, 'name' => 'member_one'],
    ]);

    $context = $this->runBatch(['mukurtu_cms_v3_cultural_protocols', 'test_after_groups']);

    $this->assertTrue($this->userExists('protocol_one'));
    $this->assertFalse($this->userExists('member_one'), 'Nothing after the failed group migration ran.');
    $this->assertSame('Cultural Protocols', $context['results']['stopped_by']);
    $this->assertSame(1, $context['results']['failures']);
    $this->assertContains('Migration stopped: 1 Cultural Protocols item failed to migrate. Nothing after this step was migrated.', $context['sandbox']['messages']);
  }

  /**
   * Tests that a failed row elsewhere doesn't stop the run.
   */
  public function testOtherFailureContinues(): void {
    $this->createUserMigration('test_before_groups', [
      ['id' => 1, 'name' => ['broken']],
    ]);
    $this->createUserMigration('test_after_groups', [
      ['id' => 1, 'name' => 'member_one'],
    ]);

    $context = $this->runBatch(['test_before_groups', 'test_after_groups']);

    $this->assertTrue($this->userExists('member_one'));
    $this->assertArrayNotHasKey('stopped_by', $context['results']);
    $this->assertSame(0, $context['results']['failures']);
    $this->assertSame(1, $context['results']['row_failures']);
  }

  /**
   * Tests that a row that failed is retried on the next run.
   */
  public function testFailedRowIsRetried(): void {
    $this->createUserMigration('mukurtu_cms_v3_cultural_protocols', [
      ['id' => 1, 'name' => ['broken']],
    ]);
    $this->runBatch(['mukurtu_cms_v3_cultural_protocols']);

    $id_map = \Drupal::service('plugin.manager.migration')->createInstance('mukurtu_cms_v3_cultural_protocols')->getIdMap();
    $this->assertSame(MigrateIdMapInterface::STATUS_FAILED, (int) $id_map->getRowBySource(['id' => 1])['source_row_status']);
    $this->assertSame(1, $id_map->messageCount());

    // The cause is fixed, and the migration is run again.
    $this->createUserMigration('mukurtu_cms_v3_cultural_protocols', [
      ['id' => 1, 'name' => 'protocol_one'],
    ]);
    $context = $this->runBatch(['mukurtu_cms_v3_cultural_protocols']);

    $id_map = \Drupal::service('plugin.manager.migration')->createInstance('mukurtu_cms_v3_cultural_protocols')->getIdMap();
    $this->assertTrue($this->userExists('protocol_one'));
    $this->assertSame(MigrateIdMapInterface::STATUS_IMPORTED, (int) $id_map->getRowBySource(['id' => 1])['source_row_status']);
    $this->assertSame(0, $id_map->messageCount(), "The failed row's old message was cleared.");
    $this->assertArrayNotHasKey('stopped_by', $context['results']);
  }

  /**
   * Tests the results page error when the run was stopped.
   */
  public function testFinishedReportsStoppedRun(): void {
    \Drupal::service('tempstore.private')->get('mukurtu_migrate')->set('create_landing_page', FALSE);

    MukurtuMigrateImportBatch::finished(TRUE, [
      'successes' => 0,
      'failures' => 1,
      'row_created_or_updated' => 1,
      'row_failures' => 1,
      'row_ignored' => 0,
      'stopped_by' => 'Cultural Protocols',
    ], [], '1');

    $errors = array_map('strval', \Drupal::messenger()->all()['error'] ?? []);
    $this->assertContains('The migration was stopped because some Cultural Protocols items failed to migrate. The memberships and content that depend on them were not migrated. Fix the problems listed in the log, then run the migration again. Failed items will be retried.', $errors);
    $this->assertContains('Migration process not completed', $errors);
  }

}
