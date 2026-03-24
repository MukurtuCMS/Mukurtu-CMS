<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_community_records\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Base class for Mukurtu Community Records kernel tests.
 */
abstract class CommunityRecordTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'path',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_community_records',
    'mukurtu_core',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');

    // A bundle that supports community records.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // A bundle that does NOT have the community records field.
    NodeType::create(['type' => 'basic_page', 'name' => 'Basic Page'])->save();

    // Create the field storage for field_mukurtu_original_record.
    FieldStorageConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => 1,
    ])->save();

    // Attach the field to the 'page' bundle only.
    FieldConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Original Record',
    ])->save();

    node_access_rebuild();
  }

  /**
   * Build and save an unsaved 'page' node.
   *
   * @param string $title
   *   The node title.
   * @param \Drupal\node\Entity\Node|null $originalRecord
   *   If provided, sets this as the original record reference.
   *
   * @return \Drupal\node\Entity\Node
   */
  protected function buildRecord(string $title, ?Node $originalRecord = NULL): Node {
    $values = [
      'type' => 'page',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ];
    if ($originalRecord !== NULL) {
      $values['field_mukurtu_original_record'] = $originalRecord->id();
    }
    return Node::create($values);
  }

}
