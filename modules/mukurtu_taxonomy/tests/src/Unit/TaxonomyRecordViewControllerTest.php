<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Unit;

use Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController;
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

}
