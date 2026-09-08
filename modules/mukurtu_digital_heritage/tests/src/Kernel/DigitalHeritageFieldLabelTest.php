<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_digital_heritage\Entity\DigitalHeritage;
use Drupal\mukurtu_digital_heritage\Entity\IndigenousKnowledgeKeepers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that DigitalHeritage/IndigenousKnowledgeKeepers field labels and
 * descriptions are translatable.
 */
#[Group('mukurtu_digital_heritage')]
class DigitalHeritageFieldLabelTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   */
  #[DataProvider('digitalHeritageFieldProvider')]
  public function testDigitalHeritageFieldLabelIsTranslatable(string $fieldName, string $expectedLabel): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = DigitalHeritage::bundleFieldDefinitions($entityType, '', []);

    $label = $definitions[$fieldName]->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals($expectedLabel, (string) $label);
  }

  public static function digitalHeritageFieldProvider(): array {
    return [
      'field_cultural_narrative' => ['field_cultural_narrative', 'Cultural Narrative'],
      'field_description' => ['field_description', 'Description'],
      'field_coverage_description' => ['field_coverage_description', 'Location Description'],
      'field_traditional_knowledge' => ['field_traditional_knowledge', 'Traditional Knowledge'],
      'field_date_description' => ['field_date_description', 'Date Description'],
      'field_source' => ['field_source', 'Source'],
      'field_summary' => ['field_summary', 'Summary'],
      'field_identifier' => ['field_identifier', 'Identifier'],
      'field_rights_and_usage' => ['field_rights_and_usage', 'Rights and Usage'],
      'field_knowledge_keepers' => ['field_knowledge_keepers', 'Citing Indigenous Elders and Knowledge Keepers'],
      'field_transcription' => ['field_transcription', 'Transcription'],
    ];
  }

  /**
   */
  #[DataProvider('knowledgeKeeperDescriptionProvider')]
  public function testKnowledgeKeeperDescriptionIsTranslatable(string $fieldName): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('paragraph');
    $definitions = IndigenousKnowledgeKeepers::bundleFieldDefinitions($entityType, '', []);

    $description = $definitions[$fieldName]->getDescription();
    $this->assertInstanceOf(TranslatableMarkup::class, $description);
  }

  public static function knowledgeKeeperDescriptionProvider(): array {
    return [
      'field_nation' => ['field_nation'],
      'field_treaty_territory' => ['field_treaty_territory'],
      'field_teaching' => ['field_teaching'],
    ];
  }

}
