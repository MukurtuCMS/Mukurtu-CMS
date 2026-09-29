<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareUserContent;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\migrate\destination\EntityUser;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the import wizard's user destination is scoped to the wizard.
 *
 * ProtocolAwareUserContent used to be swapped in for core's entity:user
 * destination site-wide, so the Mukurtu 3 to 4 user migrations ran through
 * it too: uid 1 refused to update, every migrated password was stripped and
 * blocked legacy users were activated. The wizard now selects the class by
 * its own plugin id and entity:user stays core's EntityUser.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareUserContent
 * @see \Drupal\mukurtu_import\Entity\MukurtuImportStrategy::toDefinition()
 */
#[Group('mukurtu_import')]
class ImportUserDestinationScopeTest extends MukurtuImportTestBase {

  /**
   * Tests that the wizard selects the import user destination for users only.
   */
  public function testWizardSelectsImportUserDestination(): void {
    $file = $this->createCsvFile([['Username', 'Email'], ['someone', 'someone@example.com']]);

    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('user');
    $strategy->setTargetBundle('user');
    $strategy->setMapping([]);
    $this->assertSame(ProtocolAwareUserContent::PLUGIN_ID, $strategy->toDefinition($file)['destination']['plugin']);

    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $this->assertSame('entity:node', $strategy->toDefinition($file)['destination']['plugin']);
  }

  /**
   * Tests that the two destination plugins resolve to the right classes.
   */
  public function testDestinationPluginClasses(): void {
    $manager = $this->container->get('plugin.manager.migrate.destination');

    $this->assertSame(EntityUser::class, $manager->getDefinition('entity:user')['class'], 'entity:user is no longer core\'s destination.');

    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'source' => ['plugin' => 'embedded_data', 'data_rows' => [], 'ids' => ['uid' => ['type' => 'integer']]],
      'process' => [],
      'destination' => ['plugin' => ProtocolAwareUserContent::PLUGIN_ID],
    ]);
    $destination = $migration->getDestinationPlugin();
    $this->assertInstanceOf(ProtocolAwareUserContent::class, $destination);
    $this->assertSame(['uid'], array_keys($destination->getIds()), 'The import user destination is not bound to the user entity type.');
  }

  /**
   * Tests that a plain entity:user migration keeps core's behaviour.
   *
   * This is the shape of the Mukurtu 3 to 4 user migrations: uid 1 is
   * updated in place, and a blocked source user stays blocked.
   */
  public function testEntityUserMigrationKeepsCoreBehaviour(): void {
    // The test base's current user is the first account created, uid 1.
    $this->assertSame(1, (int) $this->currentUser->id());
    $this->assertNotSame('Pacific/Auckland', User::load(1)->getTimeZone());

    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          [
            'uid' => 1,
            'name' => 'legacy_admin',
            'mail' => 'legacy-admin@example.com',
            'status' => 1,
            'timezone' => 'Pacific/Auckland',
          ],
          [
            'uid' => 50,
            'name' => 'blocked_legacy',
            'mail' => 'blocked@example.com',
            'status' => 0,
            'timezone' => 'UTC',
          ],
        ],
        'ids' => ['uid' => ['type' => 'integer']],
      ],
      'process' => [
        'uid' => 'uid',
        'name' => 'name',
        'mail' => 'mail',
        'status' => 'status',
        'timezone' => 'timezone',
      ],
      'destination' => ['plugin' => 'entity:user'],
    ]);
    $original_name = User::load(1)->getAccountName();

    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $executable->import());
    $this->assertSame(0, $migration->getIdMap()->errorCount(), 'The entity:user migration recorded failed rows.');

    $admin = User::load(1);
    $this->assertSame('Pacific/Auckland', $admin->getTimeZone(), 'uid 1 was not updated.');
    $this->assertNotSame($original_name, $admin->getAccountName());

    $blocked = User::load(50);
    $this->assertNotNull($blocked, 'The blocked user was not migrated.');
    $this->assertTrue($blocked->isBlocked(), 'The blocked legacy user was activated.');
  }

}
