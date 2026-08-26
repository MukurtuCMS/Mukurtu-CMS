<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Controller\ImportLogController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests access control for the Import Logs pages (issue #1417).
 *
 * Covers the controller directly (rather than via BrowserTestBase) since
 * this project's CI only runs the kernel test suite.
 */
class ImportLogAccessTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('mukurtu_import', ['mukurtu_import_log']);
  }

  /**
   * Insert a log row owned by the given user, return its id.
   */
  protected function insertLogRow(int $uid): int {
    /** @var \Drupal\mukurtu_import\MukurtuImportLogStorage $storage */
    $storage = $this->container->get('mukurtu_import.log_storage');
    return $storage->log([
      'import_id' => $this->container->get('uuid')->generate(),
      'migration_id' => 'test_migration',
      'uid' => $uid,
      'fid' => NULL,
      'filename' => 'test.csv',
      'entity_type_id' => 'node',
      'bundle' => 'protocol_aware_content',
      'success' => 1,
      'count_processed' => 1,
      'count_created' => 1,
      'count_updated' => 0,
      'count_failed' => 0,
      'count_ignored' => 0,
      'messages' => '',
      'timestamp' => 1000,
    ]);
  }

  /**
   * A user only sees their own row via access(), never another user's,
   * unless granted the 'view any' permission.
   */
  public function testAccessScopedToOwnRowUnlessGrantedViewAny() {
    $user_a = $this->createUser([], ['access mukurtu import']);
    $user_b = $this->createUser([], ['access mukurtu import']);

    $row_a_id = $this->insertLogRow((int) $user_a->id());
    $row_b_id = $this->insertLogRow((int) $user_b->id());

    /** @var \Drupal\mukurtu_import\Controller\ImportLogController $controller */
    $controller = ImportLogController::create($this->container);

    // User A can access their own row.
    $this->assertTrue($controller->access($row_a_id, $user_a)->isAllowed());

    // User A cannot access user B's row.
    $this->assertFalse($controller->access($row_b_id, $user_a)->isAllowed());

    // Grant user A the 'view any' permission; now they can see user B's row.
    $user_a->addRole($this->drupalCreateRole(['view any mukurtu_import_log']));
    $this->assertTrue($controller->access($row_b_id, $user_a)->isAllowed());
  }

  /**
   * A nonexistent row id is allowed at the access-check stage so the
   * controller's own 404 fires, rather than leaking existence via a 403.
   */
  public function testAccessAllowsNonexistentRowToFallThroughTo404() {
    $user_a = $this->createUser([], ['access mukurtu import']);
    $controller = ImportLogController::create($this->container);
    $this->assertTrue($controller->access(999999, $user_a)->isAllowed());
  }

  /**
   * The overview listing is scoped to the current user's own rows unless
   * they hold the 'view any' permission.
   */
  public function testOverviewListingScopedToOwnRowsUnlessGrantedViewAny() {
    $user_a = $this->createUser([], ['access mukurtu import']);
    $user_b = $this->createUser([], ['access mukurtu import']);

    $this->insertLogRow((int) $user_a->id());
    $this->insertLogRow((int) $user_b->id());

    $controller = ImportLogController::create($this->container);

    // As user A (no 'view any'), only their own row appears.
    $this->setCurrentUser($user_a);
    $build = $controller->overview(Request::create('/admin/import/logs'));
    $this->assertCount(1, $build['import_log_table']['#rows']);

    // Grant 'view any'; now both rows appear.
    $user_a->addRole($this->drupalCreateRole(['view any mukurtu_import_log']));
    $this->setCurrentUser($user_a);
    $build = $controller->overview(Request::create('/admin/import/logs'));
    $this->assertCount(2, $build['import_log_table']['#rows']);
  }

}
