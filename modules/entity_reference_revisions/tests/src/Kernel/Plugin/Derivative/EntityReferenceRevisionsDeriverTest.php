<?php

namespace Drupal\Tests\entity_reference_revisions\Kernel\Plugin\Derivative;

use Drupal\entity_reference_revisions\Plugin\migrate\destination\EntityReferenceRevisions;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrateDestinationPluginManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the migration deriver.
 *
 * @group entity_reference_revisions
 */
#[RunTestsInSeparateProcesses]
#[Group('entity_reference_revisions')]
class EntityReferenceRevisionsDeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'entity_reference_revisions',
    'user',
    'entity_test',
    'entity_composite_relationship_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Tests deriver.
   */
  public function testDestinationDeriver() {
    /** @var MigrateDestinationPluginManager $migrationDestinationManager */
    $migrationDestinationManager = \Drupal::service('plugin.manager.migrate.destination');

    $destination = $migrationDestinationManager->getDefinition('entity_reference_revisions:entity_test_composite');
    $this->assertEquals(EntityReferenceRevisions::class, $destination['class']);
  }



}
