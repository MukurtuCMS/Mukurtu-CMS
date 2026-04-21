<?php

declare(strict_types = 1);

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests generation of highlighted excerpts by the "Highlight" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Highlight
 */
#[RunTestsInSeparateProcesses]
class HighlightExcerptTest extends ProcessorTestBase {

  use CommentTestTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('highlight');

    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'node',
      'field_name' => 'body',
    ])->save();
    $this->createContentType(['type' => 'article']);
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'comment',
      'field_name' => 'comment_body',
    ])->save();
    $this->addDefaultCommentField('node', 'article');
  }

  /**
   * Tests the generation of excerpts from multiple aggregated fields.
   */
  public function testExcerptGenerationMultipleAggregatedFields(): void {
    // Set up two aggregated fields, one date field that shouldn't matter and
    // one field containing the fulltext contents.
    $changed_field = (new Field($this->index, 'changed'))
      ->setType('date')
      ->setPropertyPath('aggregated_field')
      ->setLabel('Changed')
      ->setConfiguration([
        'type' => 'union',
        'fields' => [
          Utility::createCombinedId('entity:comment', 'changed'),
          Utility::createCombinedId('entity:node', 'changed'),
        ],
      ]);
    $this->index->addField($changed_field);

    $body_field = (new Field($this->index, 'content'))
      ->setType('text')
      ->setPropertyPath('aggregated_field')
      ->setLabel('Body')
      ->setConfiguration([
        'type' => 'union',
        'fields' => [
          Utility::createCombinedId('entity:comment', 'comment_body'),
          Utility::createCombinedId('entity:node', 'body'),
        ],
      ]);
    $this->index->addField($body_field);

    $this->index->save();

    // Create a node and comment, both of which should contain the word "test"
    // in the "content" aggregated field (but not anywhere else).
    $node = Node::create([
      'type' => 'article',
      'title' => 'Foo',
      'body' => 'This is a test for the excerpt.',
      'uid' => 0,
    ]);
    $node->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'name' => 'Carla',
      'mail' => 'foo@example.com',
      'comment_body' => 'Comment on the test node.',
      'comment_type' => 'comment',
      'field_name' => 'comment',
      'pid' => 0,
      'uid' => 0,
      'status' => 1,
    ]);
    $comment->save();

    $this->indexItems();

    // Now search for "test" and verify that the excerpt is not empty and
    // contains the highlighted keyword as expected.
    $results = $this->index->query()
      ->keys('test')
      ->execute();
    $this->assertEquals(2, $results->getResultCount());
    $items = $results->getResultItems();
    $this->assertCount(2, $items);
    $node_item_id = "entity:node/{$node->id()}:en";
    $this->assertArrayHasKey($node_item_id, $items);
    $comment_item_id = "entity:comment/{$comment->id()}:en";
    $this->assertArrayHasKey($comment_item_id, $items);

    $node_result = $items[$node_item_id];
    $excerpt = $node_result->getExcerpt();
    $this->assertNotEmpty($excerpt);
    $this->assertStringContainsString('<strong>test</strong>', $excerpt);

    $comment_result = $items[$comment_item_id];
    $excerpt = $comment_result->getExcerpt();
    $this->assertNotEmpty($excerpt);
    $this->assertStringContainsString('<strong>test</strong>', $excerpt);

    // Simulate a backend that includes the field values in the search results
    // and verify that this works correctly in that scenario, too.
    $processor = $this->createMock(ProcessorInterface::class);
    $processor->method('getPluginId')->willReturn('test');
    $processor->method('supportsStage')
      ->willReturnCallback(function (string $stage): bool {
        return $stage === ProcessorInterface::STAGE_POSTPROCESS_QUERY;
      });
    $processor->method('getWeight')->willReturn(-50);
    $processor->method('postprocessSearchResults')
      ->willReturnCallback(function (ResultSetInterface $results) use ($node_item_id, $comment_item_id): void {
        foreach ($results->getResultItems() as $item_id => $item) {
          // Add a flag so we can make sure this processor ran.
          $item->setExtraData(static::class, TRUE);

          $changed_field = clone $this->index->getField('changed');
          $item->setField('changed', $changed_field);
          $content_field = clone $this->index->getField('content');
          $item->setField('content', $content_field);
          switch ($item_id) {
            case $node_item_id:
              $changed_field->addValue(1234567890);
              $content_field->addValue('This is a test for the excerpt.');
              break;

            case $comment_item_id:
              $changed_field->addValue(1234567890);
              $content_field->addValue('Comment on the test node.');
              break;

            default:
              assert(FALSE, "Unexpected item ID \"$item_id\".");
          }
        }
      });
    $this->index->addProcessor($processor);

    // Repeat the search and checks, as above.
    $results = $this->index->query()
      ->keys('test')
      ->execute();
    $this->assertEquals(2, $results->getResultCount());
    $items = $results->getResultItems();
    $this->assertCount(2, $items);
    $this->assertArrayHasKey($node_item_id, $items);
    $this->assertArrayHasKey($comment_item_id, $items);

    $node_result = $items[$node_item_id];
    $this->assertNotEmpty($node_result->getExtraData(static::class));
    $excerpt = $node_result->getExcerpt();
    $this->assertNotEmpty($excerpt);
    $this->assertStringContainsString('<strong>test</strong>', $excerpt);

    $comment_result = $items[$comment_item_id];
    $this->assertNotEmpty($comment_result->getExtraData(static::class));
    $excerpt = $comment_result->getExcerpt();
    $this->assertNotEmpty($excerpt);
    $this->assertStringContainsString('<strong>test</strong>', $excerpt);
  }

}
