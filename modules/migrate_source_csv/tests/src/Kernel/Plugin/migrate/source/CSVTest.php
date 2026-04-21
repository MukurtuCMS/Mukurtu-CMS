<?php

namespace Drupal\Tests\migrate_source_csv\Kernel\Plugin\migrate\source;

use Drupal\node\Entity\Node;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * @coversDefaultClass \Drupal\migrate_source_csv\Plugin\migrate\source\CSV
 *
 * @group migrate_source_csv
 */
class CSVTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'node',
    'datetime',
    'migrate',
    'migrate_source_csv',
    'migrate_source_csv_test',
  ];

  /**
   * Tests execution of a migration sourced from CSV.
   */
  public function testMigrate(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['migrate_source_csv_test']);

    /** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationManager */
    $migrationManager = $this->container->get('plugin.manager.migration');
    $migration = $migrationManager->createInstance('migrate_csv_test');
    $this->executeMigration($migration);
    $node = Node::load(1);
    $this->assertEquals($node->label(), 'Justin Dean');
    $this->assertEquals($node->get('field_first_name')->value, 'Justin');
    $this->assertEquals($node->get('field_last_name')->value, 'Dean');
    $this->assertEquals($node->get('field_email')->value, 'jdean0@example.com');
    $this->assertEquals($node->get('field_ip_address')->value, '60.242.130.40');
    $this->assertEquals($node->get('field_dob')->value, '1955-01-05');
  }

}
