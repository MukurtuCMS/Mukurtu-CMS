<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\Entity\Comment;
use Drupal\node\Entity\Node;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Custom value" processor.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\CustomValue
 */
#[RunTestsInSeparateProcesses]
class CustomValueTest extends ProcessorTestBase {

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $entities = [];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('custom_value');

    // Add three fields: one for nodes, one for comments and one for both.
    $field = new Field($this->index, 'custom_value_nodes');
    $field->setType('string');
    $field->setPropertyPath('custom_value');
    $field->setLabel('Item Type');
    $field->setConfiguration(['value' => '[node:type]']);
    $this->index->addField($field);

    $field = new Field($this->index, 'custom_value_comments');
    $field->setType('string');
    $field->setPropertyPath('custom_value');
    $field->setLabel('Comment Author');
    $field->setConfiguration(['value' => '[comment:author]']);
    $this->index->addField($field);

    $field = new Field($this->index, 'custom_value_both');
    $field->setType('string');
    $field->setPropertyPath('custom_value');
    $field->setLabel('Type/Author');
    $field->setConfiguration(['value' => '[node:type] [comment:author]']);
    $this->index->addField($field);

    $field = new Field($this->index, 'custom_value_fixed');
    $field->setType('string');
    $field->setPropertyPath('custom_value');
    $field->setLabel('Some value');
    $field->setConfiguration(['value' => 'Value without tokens']);
    $this->index->addField($field);

    $this->index->save();

    // Create a test node and test comment.
    $this->entities['node'] = Node::create([
      'title' => 'Test',
      'type' => 'article',
    ]);
    $this->entities['node']->save();

    $this->entities['comment'] = Comment::create([
      'subject' => 'My comment title',
      'uid' => 0,
      'name' => 'test author',
      'mail' => 'mail@example.com',
      'entity_type' => 'node',
      'field_name' => 'comment',
      'entity_id' => $this->entities['node']->id(),
      'comment_type' => 'node',
      'status' => 1,
    ]);
    $this->entities['comment']->save();
  }

  /**
   * Tests extracting the field for a search item.
   *
   * @covers ::addFieldValues
   */
  public function testItemFieldExtraction() {
    // Test field value on node.
    $node = $this->entities['node'];
    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);

    // Extract field values and check the value of our field.
    $fields = $item->getFields();
    $expected = ['article'];
    $this->assertEquals($expected, $fields['custom_value_nodes']->getValues());
    $this->assertEquals([], $fields['custom_value_comments']->getValues());
    $this->assertEquals($expected, $fields['custom_value_both']->getValues());
    $this->assertEquals(['Value without tokens'], $fields['custom_value_fixed']->getValues());

    // Test field value on comment.
    $comment = $this->entities['comment'];
    $id = Utility::createCombinedId('entity:node', $comment->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $comment->getTypedData(), $id);

    // Extract field values and check the value of our field.
    $fields = $item->getFields();
    $expected = ['test author'];
    $this->assertEquals([], $fields['custom_value_nodes']->getValues());
    $this->assertEquals($expected, $fields['custom_value_comments']->getValues());
    $this->assertEquals($expected, $fields['custom_value_both']->getValues());
    $this->assertEquals(['Value without tokens'], $fields['custom_value_fixed']->getValues());
  }

}
