<?php

namespace Drupal\Tests\term_merge\Functional;

use Drupal\node\Entity\Node;
use Drupal\term_merge\TermMerger;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests term merging for nodes.
 *
 * @group term_merge
 */
class TermMergerNodeCrudTest extends MergeTermsTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'field',
    'node',
    'term_merge',
    'taxonomy',
    'text',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->setUpContentType();
  }

  /**
   * Tests taxonomy term references are updated in a node after a term merge.
   *
   * @test
   */
  public function nodeReferencesAreUpdated(): void {
    $firstTerm = reset($this->terms);
    $node = $this->createNode(['field_terms' => ['target_id' => $firstTerm->id()]]);

    $migrator = \Drupal::service('term_reference_change.migrator');
    $dispatcher = \Drupal::service('event_dispatcher');
    $sut = new TermMerger(\Drupal::service('entity_type.manager'), $migrator, $dispatcher);
    $new_term = $sut->mergeIntoNewTerm($this->terms, 'NewTerm');

    /** @var \Drupal\node\Entity\Node $loaded_node */
    $loaded_node = Node::load($node->id());
    $referenced_terms = $loaded_node->field_terms->getValue();
    self::assertCount(1, $referenced_terms);
    $first_reference = reset($referenced_terms);
    self::assertEquals($new_term->id(), $first_reference['target_id']);
  }

  /**
   * Tests a node with both term references has a single value after a merge.
   *
   * @test
   */
  public function ifNodeReferencesBothTermsItWillOnlyReferenceTargetTermOnce(): void {
    $first_term = reset($this->terms);
    $last_term = end($this->terms);
    $values = [
      'field_terms' => ['target_id' => $first_term->id()],
      ['target_id' => $last_term->id()],
    ];
    $node = $this->createNode($values);

    $migrator = \Drupal::service('term_reference_change.migrator');
    $dispatcher = \Drupal::service('event_dispatcher');
    $sut = new TermMerger(\Drupal::service('entity_type.manager'), $migrator, $dispatcher);
    $new_term = $sut->mergeIntoNewTerm($this->terms, 'NewTerm');

    /** @var \Drupal\node\Entity\Node $loaded_node */
    $loaded_node = Node::load($node->id());
    $referenced_terms = $loaded_node->field_terms->getValue();
    self::assertCount(1, $referenced_terms);
    $first_reference = reset($referenced_terms);
    self::assertEquals($new_term->id(), $first_reference['target_id']);
  }

  /**
   * Set up a content type for testing purposes.
   */
  private function setUpContentType() {
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);
    $this->createEntityReferenceField('node', 'page', 'field_terms', 'Terms', 'taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 2;
  }

}
