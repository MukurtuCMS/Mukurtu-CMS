<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_migrate\Batch\MukurtuMigrateImportBatch;

/**
 * Tests the messaging and landing-page-creation gating logic in
 * MukurtuMigrateImportBatch::finished().
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154
 */
class MukurtuMigrateImportBatchFinishedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_drupal_ui',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * Config schema is not installed for every module referenced by
   * finished() (e.g. pathauto, which is only a soft dependency exercised
   * via a direct config save); disable strict schema checking rather than
   * pull in modules unrelated to what's under test here.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * A sentinel front page value used to detect whether finished() touched
   * system.site:page.front.
   */
  const SENTINEL_FRONT_PAGE = '/sentinel-front-page';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set a sentinel so we can tell whether the "set default front page"
    // branch ran.
    \Drupal::service('config.factory')
      ->getEditable('system.site')
      ->set('page.front', self::SENTINEL_FRONT_PAGE)
      ->save();

    // Skip the landing-page-service path entirely; that's tested elsewhere,
    // and the "set default front page" branch is the simpler branch that
    // still exercises the row_failures gate.
    \Drupal::service('tempstore.private')
      ->get('mukurtu_migrate')
      ->set('create_landing_page', FALSE);
  }

  /**
   * Reads all currently queued messenger messages of a given type as
   * plain strings.
   */
  protected function getMessengerMessages(string $type): array {
    $messages = \Drupal::messenger()->all()[$type] ?? [];
    return array_map('strval', $messages);
  }

  /**
   * The current value of system.site:page.front.
   */
  protected function getFrontPage(): ?string {
    return \Drupal::service('config.factory')->get('system.site')->get('page.front');
  }

  /**
   * A clean success (rows created, no task or row failures) shows the
   * success message, no failure/no-op messages, and still sets the default
   * front page.
   */
  public function testCleanSuccess(): void {
    $results = [
      'successes' => 1,
      'failures' => 0,
      'row_created_or_updated' => 5,
      'row_failures' => 0,
      'row_ignored' => 0,
    ];

    MukurtuMigrateImportBatch::finished(TRUE, $results, [], '1');

    $errors = $this->getMessengerMessages('error');
    $warnings = $this->getMessengerMessages('warning');
    $statuses = $this->getMessengerMessages('status');

    $this->assertNotContains('Some items failed to migrate.', $errors);
    $this->assertNotContains('No content was created or updated for this migration task.', $warnings);
    $this->assertTrue((bool) array_filter($statuses, fn($m) => str_contains($m, 'Completed 1 migration task successfully')));

    // Landing page creation was skipped, so the simpler "default front
    // page" branch should have run.
    $this->assertSame('/node', $this->getFrontPage());
  }

  /**
   * Row-level failures (even with the overall task reported as
   * "successful") surface the new error message, and block the
   * landing-page-creation gate.
   */
  public function testRowFailuresSurfaceErrorAndBlockLandingPageGate(): void {
    $results = [
      'successes' => 1,
      'failures' => 0,
      'row_created_or_updated' => 3,
      'row_failures' => 2,
      'row_ignored' => 0,
    ];

    MukurtuMigrateImportBatch::finished(TRUE, $results, [], '1');

    $errors = $this->getMessengerMessages('error');
    $warnings = $this->getMessengerMessages('warning');

    $this->assertContains('Some items failed to migrate.', $errors);
    $this->assertNotContains('No content was created or updated for this migration task.', $warnings);

    // The gate is blocked, so the front page should be untouched.
    $this->assertSame(self::SENTINEL_FRONT_PAGE, $this->getFrontPage());
  }

  /**
   * A silent no-op (rows processed, but nothing created/updated, and no
   * reported failures) surfaces the new warning message instead of the
   * error, and does not block the landing-page-creation gate (which is
   * only gated on row_failures).
   */
  public function testSilentNoopSurfacesWarningAndDoesNotBlockLandingPageGate(): void {
    $results = [
      'successes' => 1,
      'failures' => 0,
      'row_created_or_updated' => 0,
      'row_failures' => 0,
      'row_ignored' => 4,
    ];

    MukurtuMigrateImportBatch::finished(TRUE, $results, [], '1');

    $errors = $this->getMessengerMessages('error');
    $warnings = $this->getMessengerMessages('warning');

    $this->assertNotContains('Some items failed to migrate.', $errors);
    $this->assertContains('No content was created or updated for this migration task.', $warnings);

    $this->assertSame('/node', $this->getFrontPage());
  }

}
