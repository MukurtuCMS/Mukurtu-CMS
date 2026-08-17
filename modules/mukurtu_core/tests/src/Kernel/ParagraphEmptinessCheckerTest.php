<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests the mukurtu_core.paragraph_emptiness_checker service.
 */
class ParagraphEmptinessCheckerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'text',
    'entity_reference_revisions',
    'paragraphs',
    'geofield',
    'leaflet',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraph');

    ParagraphsType::create(['id' => 'test_section', 'label' => 'Test Section'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'test_section',
      'label' => 'Text',
    ])->save();
  }

  /**
   * A paragraph with no field values is considered empty.
   */
  public function testEmptyParagraphIsEmpty() {
    $paragraph = Paragraph::create(['type' => 'test_section']);
    $checker = \Drupal::service('mukurtu_core.paragraph_emptiness_checker');
    $this->assertTrue($checker->isEmpty($paragraph));
  }

  /**
   * A paragraph with one field set is not considered empty.
   */
  public function testFilledParagraphIsNotEmpty() {
    $paragraph = Paragraph::create(['type' => 'test_section', 'field_text' => 'Hello']);
    $checker = \Drupal::service('mukurtu_core.paragraph_emptiness_checker');
    $this->assertFalse($checker->isEmpty($paragraph));
  }

}
