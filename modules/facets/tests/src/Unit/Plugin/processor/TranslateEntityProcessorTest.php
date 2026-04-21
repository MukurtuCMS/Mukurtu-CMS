<?php

namespace Drupal\Tests\facets\Unit\Plugin\processor;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\processor\TranslateEntityProcessor;
use Drupal\facets\Result\Result;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit test for processor.
 *
 * @group facets
 */
class TranslateEntityProcessorTest extends UnitTestCase {

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock language manager.
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $language = new Language(['langcode' => 'en']);
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn($language);

    // Mock entity type manager.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Create and set a global container with the language manager and entity
    // type manager.
    $container = new ContainerBuilder();
    $container->set('language_manager', $this->languageManager);
    $container->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::setContainer($container);
  }

  /**
   * Provides mock data for the tests in this class.
   *
   * We create a data definition for both entity reference and entity reference
   * revision field types so we can test with both the label tranformation.
   *
   * @return array
   *   The facet and results test data.
   */
  public function facetDataProvider() {
    $data = [];
    foreach (['entity_reference', 'entity_reference_revision'] as $field_type) {
      // Mock the typed data chain.
      $target_field_definition = $this->createMock(EntityDataDefinition::class);
      $target_field_definition->expects($this->once())
        ->method('getEntityTypeId')
        ->willReturn('entity_type');
      $property_definition = $this->createMock(DataReferenceDefinitionInterface::class);
      $property_definition->expects($this->any())
        ->method('getTargetDefinition')
        ->willReturn($target_field_definition);
      $property_definition->expects($this->any())
        ->method('getDataType')
        ->willReturn($field_type);
      $data_definition = $this->createMock(ComplexDataDefinitionInterface::class);
      $data_definition->expects($this->any())
        ->method('getPropertyDefinition')
        ->willReturn($property_definition);
      $data_definition->expects($this->any())
        ->method('getPropertyDefinitions')
        ->willReturn([$property_definition]);

      // Create the actual facet.
      $facet = $this->createMock(Facet::class);
      $facet->expects($this->any())
        ->method('getDataDefinition')
        ->willReturn($data_definition);

      // Add a field identifier.
      $facet->expects($this->any())
        ->method('getFieldIdentifier')
        ->willReturn('testfield');

      $results = [new Result($facet, 2, 2, 5)];
      $facet->setResults($results);

      $data[$field_type][] = $facet;
      $data[$field_type][] = $results;
    }

    return $data;
  }

  /**
   * Tests that node results were correctly changed.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   A facet mock.
   * @param array $results
   *   The facet original results mock.
   *
   * @dataProvider facetDataProvider
   */
  public function testNodeResultsChanged(FacetInterface $facet, array $results) {
    // Mock a node and add the label to it.
    $node = $this->createMock(Node::class);
    $node->expects($this->any())
      ->method('label')
      ->willReturn('shaken not stirred');
    $nodes = [
      2 => $node,
    ];
    $node_storage = $this->createMock(EntityStorageInterface::class);
    $node_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturn($nodes);
    $this->entityTypeManager->expects($this->exactly(1))
      ->method('getStorage')
      ->willReturn($node_storage);

    // Set expected results.
    $expected_results = [
      ['nid' => 2, 'title' => 'shaken not stirred'],
    ];

    // Without the processor we expect the id to display.
    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['nid'], $results[$key]->getRawValue());
      $this->assertEquals($expected['nid'], $results[$key]->getDisplayValue());
    }

    // With the processor we expect the title to display.
    /** @var \Drupal\facets\Result\ResultInterface[] $filtered_results */
    $processor = new TranslateEntityProcessor([], 'translate_entity', [], $this->languageManager, $this->entityTypeManager);
    $filtered_results = $processor->build($facet, $results);
    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['nid'], $filtered_results[$key]->getRawValue());
      $this->assertEquals($expected['title'], $filtered_results[$key]->getDisplayValue());
    }
  }

  /**
   * Tests that term results were correctly changed.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   A facet mock.
   * @param array $results
   *   The facet original results mock.
   *
   * @dataProvider facetDataProvider
   */
  public function testTermResultsChanged(FacetInterface $facet, array $results) {
    // Mock term.
    $term = $this->createMock(Term::class);
    $term->expects($this->once())
      ->method('label')
      ->willReturn('Burrowing owl');
    $terms = [
      2 => $term,
    ];
    $term_storage = $this->createMock(EntityStorageInterface::class);
    $term_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturn($terms);
    $this->entityTypeManager->expects($this->exactly(1))
      ->method('getStorage')
      ->willReturn($term_storage);

    // Set expected results.
    $expected_results = [
      ['tid' => 2, 'name' => 'Burrowing owl'],
    ];

    // Without the processor we expect the id to display.
    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['tid'], $results[$key]->getRawValue());
      $this->assertEquals($expected['tid'], $results[$key]->getDisplayValue());
    }

    /** @var \Drupal\facets\Result\ResultInterface[] $filtered_results */
    $processor = new TranslateEntityProcessor([], 'translate_entity', [], $this->languageManager, $this->entityTypeManager);
    $filtered_results = $processor->build($facet, $results);

    // With the processor we expect the title to display.
    foreach ($expected_results as $key => $expected) {
      $this->assertEquals($expected['tid'], $filtered_results[$key]->getRawValue());
      $this->assertEquals($expected['name'], $filtered_results[$key]->getDisplayValue());
    }
  }

  /**
   * Test that deleted entities still in index results doesn't display.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   A facet mock.
   * @param array $results
   *   The facet original results mock.
   *
   * @dataProvider facetDataProvider
   */
  public function testDeletedEntityResults(FacetInterface $facet, array $results) {
    // Set original results.
    $term_storage = $this->createMock(EntityStorageInterface::class);
    $term_storage->expects($this->any())
      ->method('loadMultiple')
      ->willReturn([]);
    $this->entityTypeManager->expects($this->exactly(1))
      ->method('getStorage')
      ->willReturn($term_storage);

    // Processor should return nothing (and not throw an exception).
    /** @var \Drupal\facets\Result\ResultInterface[] $filtered_results */
    $processor = new TranslateEntityProcessor([], 'translate_entity', [], $this->languageManager, $this->entityTypeManager);
    $filtered_results = $processor->build($facet, $results);
    $this->assertEmpty($filtered_results);
  }

}
