<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate\process;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entity_generate plugin with process pipelines in values.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class EntityGenerateValuesTest extends KernelTestBase implements MigrateMessageInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_plus',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('taxonomy_term');

    // Create the vocabulary 'tags' so it exists before migration.
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
  }

  /**
   * Tests generating an entity with a process pipeline in the 'values'.
   */
  public function testEntityGenerateWithPipeline(): void {
    $definition = [
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          [
            'id' => 1,
            'source_name' => 'apple',
            'source_desc' => 'red fruit',
          ],
        ],
        'ids' => [
          'id' => ['type' => 'integer'],
        ],
      ],
      'process' => [
        'field_tags' => [
          'plugin' => 'entity_generate',
          'source' => 'source_name',
          'entity_type' => 'taxonomy_term',
          'bundle_key' => 'vid',
          'bundle' => 'tags',
          'value_key' => 'name',
          'values' => [
            // Test the new functionality: treating an array.
            'description' => [
              'plugin' => 'callback',
              'callable' => 'strtoupper',
              'source' => 'source_desc',
            ],
          ],
        ],
      ],
      'destination' => [
        'plugin' => 'entity:user',
      ],
    ];

    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $executable = new MigrateExecutable($migration, $this);
    $executable->import();

    // Verify the term was created.
    $term = Term::load(1);
    $this->assertNotNull($term, 'The term should have been created.');

    // Verify standard mapping worked.
    $this->assertEquals('apple', $term->getName());

    // Verify the pipeline transformation worked (red fruit -> RED FRUIT).
    $this->assertEquals('RED FRUIT', $term->getDescription());
  }

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status'): void {
    // No-op.
  }

}
