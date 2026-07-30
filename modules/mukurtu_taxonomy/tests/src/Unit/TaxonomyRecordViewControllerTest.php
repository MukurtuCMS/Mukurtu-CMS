<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController
 * @group mukurtu_taxonomy
 */
class TaxonomyRecordViewControllerTest extends UnitTestCase {

  /**
   * Returns a partial mock with the constructor disabled.
   */
  protected function getController(): TaxonomyRecordViewController {
    return $this->getMockBuilder(TaxonomyRecordViewController::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['entityTypeManager'])
      ->getMock();
  }

  /**
   * Calls the protected getSingularVocabularyLabel() via reflection.
   */
  protected function callGetLabel(TaxonomyRecordViewController $controller, string $vocab): string {
    $method = new \ReflectionMethod($controller, 'getSingularVocabularyLabel');
    $method->setAccessible(TRUE);
    return $method->invoke($controller, $vocab);
  }

  /**
   * Builds a controller wired up for testing getSingleRecord().
   *
   * Stubs the mukurtuTaxonomySettings config and an entityTypeManager wired
   * to the given node query results.
   *
   * @param array $settings
   *   Map of mukurtu_taxonomy.settings keys to values, e.g.
   *   ['person_records_enabled_vocabularies' => ['people']].
   * @param array $queryResultsByType
   *   Map of node bundle => array of matching node IDs returned by the
   *   entity query for that bundle.
   * @param \Drupal\node\NodeInterface[] $nodesById
   *   Map of node ID => node mock, used by storage::load().
   */
  protected function getControllerForRecordLookup(array $settings, array $queryResultsByType, array $nodesById = []): TaxonomyRecordViewController {
    $controller = $this->getMockBuilder(TaxonomyRecordViewController::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['entityTypeManager'])
      ->getMock();

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(fn(string $key) => $settings[$key] ?? NULL);

    $configProperty = new \ReflectionProperty($controller, 'mukurtuTaxonomySettings');
    $configProperty->setAccessible(TRUE);
    $configProperty->setValue($controller, $config);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(fn($id) => $nodesById[$id] ?? NULL);
    $storage->method('getQuery')->willReturnCallback(function () use (&$queryResultsByType) {
      $type = NULL;
      $query = $this->createMock(QueryInterface::class);
      $query->method('condition')->willReturnCallback(function ($field, $value = NULL) use ($query, &$type) {
        if ($field === 'type') {
          $type = $value;
        }
        return $query;
      });
      $query->method('accessCheck')->willReturnSelf();
      $query->method('execute')->willReturnCallback(function () use (&$type, &$queryResultsByType) {
        return $queryResultsByType[$type] ?? [];
      });
      return $query;
    });

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $controller->method('entityTypeManager')->willReturn($entityTypeManager);

    return $controller;
  }

  /**
   * Calls the protected getSingleRecord() via reflection.
   */
  protected function callGetSingleRecord(TaxonomyRecordViewController $controller, TermInterface $term, string $bundle, string $field, string $vocabularySetting): ?NodeInterface {
    $method = new \ReflectionMethod($controller, 'getSingleRecord');
    $method->setAccessible(TRUE);
    return $method->invoke($controller, $term, $bundle, $field, $vocabularySetting);
  }

  /**
   * @covers ::VOCABULARY_LABEL_MAP
   */
  public function testVocabularyLabelMapCoversAllBuiltInVocabs(): void {
    $expected = [
      'category', 'community_type', 'contributor', 'creator', 'format',
      'interpersonal_relationship', 'keywords', 'language', 'location',
      'media_tag', 'people', 'place_type', 'publisher', 'subject', 'type',
      'word_type',
    ];
    foreach ($expected as $vocab) {
      $this->assertArrayHasKey($vocab, TaxonomyRecordViewController::VOCABULARY_LABEL_MAP,
        "VOCABULARY_LABEL_MAP is missing entry for '$vocab'.");
    }
  }

  /**
   * @covers ::getSingularVocabularyLabel
   * @dataProvider mappedVocabularyProvider
   */
  public function testGetSingularVocabularyLabelMappedValues(string $vocab, string $expected): void {
    $controller = $this->getController();
    $this->assertSame($expected, $this->callGetLabel($controller, $vocab));
  }

  public static function mappedVocabularyProvider(): array {
    return [
      'keywords -> Keyword (not Keywords)' => ['keywords', 'Keyword'],
      'people -> Person (not People)' => ['people', 'Person'],
      'category -> Category' => ['category', 'Category'],
      'community_type -> Community Type' => ['community_type', 'Community Type'],
    ];
  }

  /**
   * @covers ::getSingularVocabularyLabel
   */
  public function testGetSingularVocabularyLabelUnmappedFallsBackToVocabLabel(): void {
    $vocabulary = $this->createMock(\Drupal\taxonomy\VocabularyInterface::class);
    $vocabulary->method('label')->willReturn('Custom Tags');

    $storage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $storage->method('load')->with('custom_tags')->willReturn($vocabulary);

    $entityTypeManager = $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('taxonomy_vocabulary')->willReturn($storage);

    $controller = $this->getController();
    $controller->method('entityTypeManager')->willReturn($entityTypeManager);

    $this->assertSame('Custom Tags', $this->callGetLabel($controller, 'custom_tags'));
  }

  /**
   * @covers ::getSingularVocabularyLabel
   */
  public function testGetSingularVocabularyLabelMissingVocabFallsBackToMachineName(): void {
    $storage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $storage->method('load')->with('nonexistent')->willReturn(NULL);

    $entityTypeManager = $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('taxonomy_vocabulary')->willReturn($storage);

    $controller = $this->getController();
    $controller->method('entityTypeManager')->willReturn($entityTypeManager);

    $this->assertSame('nonexistent', $this->callGetLabel($controller, 'nonexistent'));
  }

  /**
   * @covers ::title
   */
  public function testTitleReturnsVocabColonTermFormat(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('keywords');
    $term->method('label')->willReturn('Traditional Dance');

    $controller = $this->getController();
    $this->assertSame('Keyword: Traditional Dance', $controller->title($term));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordReturnsNullWhenVocabularyNotEnabled(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('location');

    $controller = $this->getControllerForRecordLookup(
      ['place_records_enabled_vocabularies' => ['other_vocab']],
      []
    );

    $this->assertNull($this->callGetSingleRecord($controller, $term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies'));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordReturnsNullWhenNoMatches(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('location');
    $term->method('id')->willReturn(42);

    $controller = $this->getControllerForRecordLookup(
      ['place_records_enabled_vocabularies' => ['location']],
      ['place' => []]
    );

    $this->assertNull($this->callGetSingleRecord($controller, $term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies'));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordReturnsNullWhenMultipleMatches(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('location');
    $term->method('id')->willReturn(42);

    $controller = $this->getControllerForRecordLookup(
      ['place_records_enabled_vocabularies' => ['location']],
      ['place' => [1, 2]]
    );

    $this->assertNull($this->callGetSingleRecord($controller, $term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies'));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordReturnsThePlaceNodeOnSingleAccessibleMatch(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('location');
    $term->method('id')->willReturn(42);

    $place = $this->createMock(NodeInterface::class);
    $place->method('access')->with('view')->willReturn(TRUE);

    $controller = $this->getControllerForRecordLookup(
      ['place_records_enabled_vocabularies' => ['location']],
      ['place' => [7]],
      [7 => $place]
    );

    $this->assertSame($place, $this->callGetSingleRecord($controller, $term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies'));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordReturnsNullWhenSingleMatchIsInaccessible(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('location');
    $term->method('id')->willReturn(42);

    $place = $this->createMock(NodeInterface::class);
    $place->method('access')->with('view')->willReturn(FALSE);

    $controller = $this->getControllerForRecordLookup(
      ['place_records_enabled_vocabularies' => ['location']],
      ['place' => [7]],
      [7 => $place]
    );

    $this->assertNull($this->callGetSingleRecord($controller, $term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies'));
  }

  /**
   * @covers ::getSingleRecord
   */
  public function testGetSingleRecordStillWorksForPersonBundle(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('people');
    $term->method('id')->willReturn(11);

    $person = $this->createMock(NodeInterface::class);
    $person->method('access')->with('view')->willReturn(TRUE);

    $controller = $this->getControllerForRecordLookup(
      ['person_records_enabled_vocabularies' => ['people']],
      ['person' => [3]],
      [3 => $person]
    );

    $this->assertSame($person, $this->callGetSingleRecord($controller, $term, 'person', 'field_other_names', 'person_records_enabled_vocabularies'));
  }

}
