<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordEntry;
use Drupal\mukurtu_dictionary\Entity\SampleSentence;
use Drupal\mukurtu_dictionary\Entity\WordList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that mukurtu_dictionary field labels and descriptions are
 * translatable.
 */
#[Group('mukurtu_dictionary')]
class DictionaryFieldLabelTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   */
  #[DataProvider('dictionaryWordFieldProvider')]
  public function testDictionaryWordFieldIsTranslatable(string $fieldName, string $property): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = DictionaryWord::bundleFieldDefinitions($entityType, '', []);

    $value = $property === 'label' ? $definitions[$fieldName]->getLabel() : $definitions[$fieldName]->getDescription();
    $this->assertInstanceOf(TranslatableMarkup::class, $value);
  }

  public static function dictionaryWordFieldProvider(): array {
    return [
      'field_coverage_description label' => ['field_coverage_description', 'label'],
      'field_contributor description' => ['field_contributor', 'description'],
      'field_definition description' => ['field_definition', 'description'],
      'field_recording description' => ['field_recording', 'description'],
      'field_word_origin description' => ['field_word_origin', 'description'],
      'field_word_type description' => ['field_word_type', 'description'],
    ];
  }

  /**
   */
  #[DataProvider('wordListFieldProvider')]
  public function testWordListFieldLabelIsTranslatable(string $fieldName): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = WordList::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions[$fieldName]->getLabel());
  }

  public static function wordListFieldProvider(): array {
    return [
      'field_summary' => ['field_summary'],
      'field_source' => ['field_source'],
      'field_coverage_description' => ['field_coverage_description'],
    ];
  }

  /**
   */
  #[DataProvider('dictionaryWordEntryFieldProvider')]
  public function testDictionaryWordEntryFieldDescriptionIsTranslatable(string $fieldName): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('paragraph');
    $definitions = DictionaryWordEntry::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions[$fieldName]->getDescription());
  }

  public static function dictionaryWordEntryFieldProvider(): array {
    return [
      'field_contributor' => ['field_contributor'],
      'field_definition' => ['field_definition'],
      'field_recording' => ['field_recording'],
      'field_word_origin' => ['field_word_origin'],
      'field_word_type' => ['field_word_type'],
    ];
  }

  public function testSampleSentenceFieldDescriptionIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('paragraph');
    $definitions = SampleSentence::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_sentence_recording']->getDescription());
  }

}
