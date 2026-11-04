<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40048().
 *
 * @see mukurtu_dictionary_update_40048()
 */
#[Group('mukurtu_dictionary')]
class SampleSentenceWidgetUpdateTest extends DictionaryTestBase {

  /**
   * The form displays the hook updates, keyed by display ID.
   */
  private const DISPLAYS = [
    'node.dictionary_word.default' => ['node', 'dictionary_word', 1],
    'paragraph.dictionary_word_entry.default' => ['paragraph', 'dictionary_word_entry', 5],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('mukurtu_dictionary', 'install');
  }

  /**
   * Creates the form displays with the pre-update legacy widget.
   */
  private function makeLegacyDisplays(): void {
    foreach (self::DISPLAYS as [$entity_type, $bundle, $weight]) {
      EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ])->setComponent('field_sample_sentences', [
        'type' => 'entity_reference_paragraphs',
        'weight' => $weight,
        'region' => 'content',
        'settings' => [
          'title' => 'Paragraph',
          'title_plural' => 'Paragraphs',
          'edit_mode' => 'open',
          'add_mode' => 'dropdown',
          'form_display_mode' => 'default',
          'default_paragraph_type' => '',
        ],
      ])->save();
    }
  }

  /**
   * Asserts a display's sample sentences widget is the collapsible one.
   */
  private function assertCollapsibleWidget(string $display_id, int $weight): void {
    $component = EntityFormDisplay::load($display_id)->getComponent('field_sample_sentences');
    $this->assertSame('paragraphs', $component['type'], $display_id);
    $this->assertSame($weight, $component['weight'], $display_id);
    $this->assertSame('content', $component['region'], $display_id);
    $this->assertSame('Sample Sentence', $component['settings']['title'], $display_id);
    $this->assertSame('Sample Sentences', $component['settings']['title_plural'], $display_id);
    $this->assertSame('summary', $component['settings']['closed_mode'], $display_id);
    $this->assertSame('collapse_edit_all', $component['settings']['features']['collapse_edit_all'], $display_id);
  }

  /**
   * Both legacy widgets are switched, keeping their position.
   */
  public function testSwitchesLegacyWidgets(): void {
    $this->makeLegacyDisplays();

    mukurtu_dictionary_update_40048();

    foreach (self::DISPLAYS as $display_id => [, , $weight]) {
      $this->assertCollapsibleWidget($display_id, $weight);
    }
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeLegacyDisplays();

    mukurtu_dictionary_update_40048();
    mukurtu_dictionary_update_40048();

    foreach (self::DISPLAYS as $display_id => [, , $weight]) {
      $this->assertCollapsibleWidget($display_id, $weight);
    }
  }

  /**
   * A widget a site already changed is left alone.
   */
  public function testCustomizedWidgetIsUntouched(): void {
    $this->makeLegacyDisplays();
    $display = EntityFormDisplay::load('node.dictionary_word.default');
    $display->setComponent('field_sample_sentences', [
      'type' => 'paragraphs',
      'weight' => 7,
      'region' => 'content',
      'settings' => [
        'title' => 'Custom',
        'edit_mode' => 'closed',
      ],
    ])->save();

    mukurtu_dictionary_update_40048();

    $component = EntityFormDisplay::load('node.dictionary_word.default')->getComponent('field_sample_sentences');
    $this->assertSame('Custom', $component['settings']['title']);
    $this->assertSame('closed', $component['settings']['edit_mode']);
    $this->assertSame(7, $component['weight']);
    $this->assertCollapsibleWidget('paragraph.dictionary_word_entry.default', 5);
  }

  /**
   * Missing displays don't error.
   */
  public function testMissingDisplaysDoNotError(): void {
    foreach (array_keys(self::DISPLAYS) as $display_id) {
      $this->assertNull(EntityFormDisplay::load($display_id));
    }

    mukurtu_dictionary_update_40048();

    foreach (array_keys(self::DISPLAYS) as $display_id) {
      $this->assertNull(EntityFormDisplay::load($display_id));
    }
  }

}
