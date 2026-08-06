<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Unit;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mukurtu_core\Event\RelatedContentProvenanceEvent;
use Drupal\mukurtu_taxonomy\EventSubscriber\RelatedContentComputationSubscriber;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mukurtu_taxonomy\EventSubscriber\RelatedContentComputationSubscriber
 * @group mukurtu_taxonomy
 */
class RelatedContentComputationSubscriberTest extends UnitTestCase {

  /**
   * Builds a mock node field storage definition.
   */
  protected function mockFieldStorageDefinition(string $type, ?string $targetType = NULL): FieldDefinitionInterface {
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('isComputed')->willReturn(FALSE);
    $field->method('getType')->willReturn($type);
    $field->method('getSetting')->willReturnCallback(fn($name) => $name === 'target_type' ? $targetType : NULL);
    return $field;
  }

  /**
   * Builds a subscriber wired to the given field storage definitions.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fields
   *   Keyed by field name.
   */
  protected function getSubscriber(array $fields): RelatedContentComputationSubscriber {
    $entityFieldManager = $this->getMockBuilder(EntityFieldManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getActiveFieldStorageDefinitions'])
      ->getMock();
    $entityFieldManager->method('getActiveFieldStorageDefinitions')->with('node')->willReturn($fields);
    return new RelatedContentComputationSubscriber($entityFieldManager);
  }

  /**
   * Builds a mock term with the given id and vocabulary (bundle).
   */
  protected function mockTerm(int $tid, string $vocabulary): TermInterface {
    $term = $this->createMock(TermInterface::class);
    $term->method('id')->willReturn($tid);
    $term->method('bundle')->willReturn($vocabulary);
    return $term;
  }

  /**
   * Builds a mock candidate node with the given field values.
   *
   * @param array $fieldValues
   *   Map of field name => raw field item value array, e.g.
   *   ['field_creator' => [['target_id' => 5]]].
   */
  protected function mockCandidate(int $nid, array $fieldValues): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($nid);
    $node->method('hasField')->willReturnCallback(fn($name) => isset($fieldValues[$name]));
    $node->method('get')->willReturnCallback(function ($name) use ($fieldValues) {
      $itemList = $this->createMock(FieldItemListInterface::class);
      $itemList->method('getValue')->willReturn($fieldValues[$name] ?? []);
      return $itemList;
    });
    return $node;
  }

  /**
   * Builds a mock record (Person/Place) node with the given other-names terms.
   */
  protected function mockRecord(int $nid, ?string $otherNamesField, array $terms): NodeInterface {
    $record = $this->createMock(NodeInterface::class);
    $record->method('id')->willReturn($nid);
    $record->method('uuid')->willReturn('record-uuid-' . $nid);
    $record->method('hasField')->willReturnCallback(fn($name) => $name === $otherNamesField);
    $record->method('get')->willReturnCallback(function ($name) use ($otherNamesField, $terms) {
      if ($name !== $otherNamesField) {
        throw new \InvalidArgumentException("Unexpected field $name");
      }
      $itemList = $this->createMock(EntityReferenceFieldItemListInterface::class);
      $itemList->method('referencedEntities')->willReturn($terms);
      return $itemList;
    });
    return $record;
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testNoOtherNamesFieldLeavesProvenanceEmpty(): void {
    $subscriber = $this->getSubscriber([]);
    $record = $this->mockRecord(1, NULL, []);
    $candidate = $this->mockCandidate(2, []);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertSame([], $event->provenance);
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testCandidateMatchingTermIsTaggedWithVocabulary(): void {
    $fields = [
      'field_creator' => $this->mockFieldStorageDefinition('entity_reference', 'taxonomy_term'),
    ];
    $subscriber = $this->getSubscriber($fields);

    $term = $this->mockTerm(10, 'creator');
    $record = $this->mockRecord(1, 'field_other_names', [$term]);
    $candidate = $this->mockCandidate(2, ['field_creator' => [['target_id' => 10]]]);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertSame(['vocabularies' => ['creator'], 'other' => FALSE], $event->provenance[2]);
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testCandidateReferencingRecordDirectlyIsTaggedOther(): void {
    $fields = [
      'field_related_content' => $this->mockFieldStorageDefinition('entity_reference', 'node'),
    ];
    $subscriber = $this->getSubscriber($fields);

    $term = $this->mockTerm(10, 'people');
    $record = $this->mockRecord(1, 'field_other_names', [$term]);
    $candidate = $this->mockCandidate(2, ['field_related_content' => [['target_id' => 1]]]);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertSame(['vocabularies' => [], 'other' => TRUE], $event->provenance[2]);
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testCandidateEmbeddingRecordUuidIsTaggedOther(): void {
    $fields = [
      'body' => $this->mockFieldStorageDefinition('text_long'),
    ];
    $subscriber = $this->getSubscriber($fields);

    $term = $this->mockTerm(10, 'people');
    $record = $this->mockRecord(1, 'field_other_names', [$term]);
    $candidate = $this->mockCandidate(2, ['body' => [['value' => 'See also [record-uuid-1]']]]);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertSame(['vocabularies' => [], 'other' => TRUE], $event->provenance[2]);
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testCandidateWithNoMatchIsLeftUntagged(): void {
    $fields = [
      'field_creator' => $this->mockFieldStorageDefinition('entity_reference', 'taxonomy_term'),
    ];
    $subscriber = $this->getSubscriber($fields);

    $term = $this->mockTerm(10, 'creator');
    $record = $this->mockRecord(1, 'field_other_names', [$term]);
    $candidate = $this->mockCandidate(2, ['field_creator' => [['target_id' => 999]]]);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertArrayNotHasKey(2, $event->provenance);
  }

  /**
   * @covers ::onRelatedContentProvenance
   */
  public function testPlaceRecordUsesOtherPlaceNamesField(): void {
    $fields = [
      'field_location' => $this->mockFieldStorageDefinition('entity_reference', 'taxonomy_term'),
    ];
    $subscriber = $this->getSubscriber($fields);

    $term = $this->mockTerm(20, 'location');
    $record = $this->mockRecord(1, 'field_other_place_names', [$term]);
    $candidate = $this->mockCandidate(2, ['field_location' => [['target_id' => 20]]]);

    $event = new RelatedContentProvenanceEvent($record, [2 => $candidate]);
    $subscriber->onRelatedContentProvenance($event);

    $this->assertSame(['vocabularies' => ['location'], 'other' => FALSE], $event->provenance[2]);
  }

}
