<?php

namespace Drupal\Tests\term_reference_change\Kernel;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\term_reference_change\ReferenceFinder;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests entities referencing a term are found.
 *
 * @group term_reference_change
 */
class ReferenceFinderTest extends KernelTestBase {

  use TaxonomyTestTrait;
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
    'taxonomy',
    'text',
    'user',
    'system',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * A vocabulary used for testing.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  private $targetVocabulary;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorage
   */
  private $termStorage;

  /**
   * A vocabulary used for testing.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  private $referencingVocabulary;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->setUpContentType();
    $this->setUpReferencingVocabulary();

    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->targetVocabulary = $this->createVocabulary();
    $this->termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * Tests referencing entities are found.
   *
   * @test
   */
  public function findsReferencingEntities() {
    $term = $this->createTerm($this->targetVocabulary);
    $referencingNode = $this->createNode(['field_terms' => ['target_id' => $term->id()]]);
    $nonReferencingNode = $this->createNode();
    $referencingTerm = $this->createTerm($this->referencingVocabulary, ['field_terms' => ['target_id' => $term->id()]]);
    $nonReferencingTerm = $this->createTerm($this->referencingVocabulary);

    $sut = new ReferenceFinder($this->entityTypeManager, \Drupal::service('entity_type.bundle.info'), \Drupal::service('entity_field.manager'));
    $references = $sut->findReferencesFor($term);

    $referencingNode = $this->entityTypeManager->getStorage('node')->load($referencingNode->id());
    $referencingTerm = $this->termStorage->load($referencingTerm->id());
    $expected = [
      'node' => [$referencingNode],
      'taxonomy_term' => [$referencingTerm],
    ];
    $this->assertEquals($expected, $references);
  }

  /**
   * Set up a content type for testing purposes.
   */
  private function setUpContentType() {
    $bundle = 'page';
    $this->createContentType([
      'type' => $bundle,
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

    $entityType = 'node';
    $fieldName = 'field_terms';
    $fieldLabel = 'Terms';
    $targetEntityType = 'taxonomy_term';
    $this->createEntityReferenceField($entityType, $bundle, $fieldName, $fieldLabel, $targetEntityType);
  }

  /**
   * Set up a vocabulary for testing purposes.
   */
  private function setUpReferencingVocabulary() {
    $this->referencingVocabulary = $this->createVocabulary();

    $entityType = 'taxonomy_term';
    $bundle = $this->referencingVocabulary->id();
    $fieldName = 'field_terms';
    $fieldLabel = 'Terms';
    $targetEntityType = 'taxonomy_term';
    $this->createEntityReferenceField($entityType, $bundle, $fieldName, $fieldLabel, $targetEntityType);
  }

}
