<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that rebuilding migration definitions doesn't check the source database.
 *
 * @see \Drupal\mukurtu_migrate\Hook\MigrationPluginsAlterHooks
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2551
 */
#[Group('mukurtu_migrate')]
class MigrationPluginsAlterHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_drupal',
    'mukurtu_migrate',
  ];

  /**
   * Rebuilds the migration definitions and returns them.
   */
  protected function rebuildDefinitions(): array {
    $manager = \Drupal::service('plugin.manager.migration');
    $manager->clearCachedDefinitions();
    return $manager->getDefinitions();
  }

  /**
   * A site with no source database shows no error after a rebuild.
   */
  public function testNoSourceDatabase(): void {
    $definitions = $this->rebuildDefinitions();

    $this->assertArrayNotHasKey('system_site', $definitions);
    $this->assertSame([], \Drupal::messenger()->messagesByType('error'));
  }

  /**
   * A site whose saved source database is unreachable shows no error.
   *
   * This is a site whose Mukurtu 3 database was removed after migrating.
   */
  public function testUnreachableSourceDatabase(): void {
    $state = \Drupal::state();
    $state->set('migrate_drupal_7', [
      'key' => 'upgrade',
      'database' => [
        'driver' => 'mysql',
        'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
        'host' => '127.0.0.1',
        'port' => '1',
        'database' => 'removed_v3',
        'username' => 'removed_v3',
        'password' => 'removed_v3',
      ],
    ]);
    $state->set('migrate.fallback_state_key', 'migrate_drupal_7');

    $definitions = $this->rebuildDefinitions();

    $this->assertArrayNotHasKey('system_site', $definitions);
    $this->assertSame([], \Drupal::messenger()->messagesByType('error'));
  }

}
