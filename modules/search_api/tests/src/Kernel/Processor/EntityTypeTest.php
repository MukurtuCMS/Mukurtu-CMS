<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entity type property.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\EntityType
 */
#[RunTestsInSeparateProcesses]
class EntityTypeTest extends ProcessorTestBase {

  use PostRequestIndexingTrait;
  use CommentTestTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * The comments created for testing.
   *
   * @var \Drupal\comment\Entity\Comment[]
   */
  protected $comments;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('entity_type');

    NodeType::create([
      'type' => 'test_node_bundle',
    ])->save();

    $entity_type_field = new Field($this->index, 'entity_type');
    $entity_type_field->setType('string');
    $entity_type_field->setPropertyPath('search_api_entity_type');
    $entity_type_field->setLabel('Entity type');
    $this->index->addField($entity_type_field);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();
  }

  /**
   * Tests that field values are added correctly.
   *
   * @covers ::addFieldValues
   */
  public function testAddFieldValues() {
    $this->nodes[0] = Node::create([
      'title' => 'Test',
      'type' => 'test_node_bundle',
    ]);
    $this->nodes[0]->save();

    $this->triggerPostRequestIndexing();
    $expected[Utility::createCombinedId('entity:node', $this->nodes[0]->id() . ':en')] = ['node'];
    $this->assertEquals($expected, $this->getEntityTypeValues(), 'Added node entity type is indexed correctly.');

    $comment_type = CommentType::create([
      'id' => 'comment',
      'target_entity_type_id' => 'node',
    ]);
    $comment_type->save();

    $this->installConfig(['comment']);
    $this->addDefaultCommentField('node', 'test_node_bundle');

    $this->comments[0] = Comment::create([
      'status' => CommentInterface::PUBLISHED,
      'entity_type' => 'node',
      'entity_id' => $this->nodes[0]->id(),
      'field_name' => 'comment',
      'body' => 'test body',
      'comment_type' => $comment_type->id(),
    ]);
    $this->comments[0]->save();

    $this->triggerPostRequestIndexing();
    $expected[Utility::createCombinedId('entity:comment', $this->comments[0]->id() . ':en')] = ['comment'];
    $this->assertEquals($expected, $this->getEntityTypeValues(), 'Added comment entity type is indexed correctly.');
  }

  /**
   * Retrieves the indexed values.
   *
   * @return string[][]
   *   The indexed "entity_type" field values for all indexed items,
   *   keyed by item ID.
   */
  protected function getEntityTypeValues(): array {
    $query = $this->index->query();
    // We don't need a query condition as we have only one node anyway.
    $results = $query->execute();
    $values = [];
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $field_values = $result->getField('entity_type')->getValues();
      sort($field_values);
      $values[$result->getId()] = $field_values;
    }
    return $values;
  }

}
