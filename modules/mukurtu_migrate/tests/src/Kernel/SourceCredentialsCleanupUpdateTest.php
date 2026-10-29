<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40209().
 *
 * @see mukurtu_migrate_update_40209()
 */
#[Group('mukurtu_migrate')]
class SourceCredentialsCleanupUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_drupal',
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
   * The saved credentials of a migrated site are deleted.
   */
  public function testSavedCredentialsDeleted(): void {
    $state = \Drupal::state();
    $state->set('migrate_drupal_7', ['key' => 'upgrade', 'database' => ['password' => 'secret']]);
    $state->set('migrate.fallback_state_key', 'migrate_drupal_7');

    $message = mukurtu_migrate_update_40209();

    $this->assertSame('Removed the saved Mukurtu CMS v3 database credentials, which were no longer needed after the migration.', $message);
    $this->assertNull($state->get('migrate_drupal_7'));
    $this->assertNull($state->get('migrate.fallback_state_key'));
  }

  /**
   * A site with no saved credentials is left alone and gets no message.
   */
  public function testNoSavedCredentials(): void {
    $this->assertNull(mukurtu_migrate_update_40209());
  }

}
