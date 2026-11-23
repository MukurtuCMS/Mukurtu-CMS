<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the collapsed summary of Biography section paragraphs.
 *
 * Paragraphs only summarizes configurable fields, and every field on this
 * bundle is code-defined, so without our override a collapsed section shows
 * only its type label.
 */
#[Group('mukurtu_person')]
class BiographySectionSummaryTest extends PersonTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ParagraphsType::create(['id' => 'formatted_text_with_title', 'label' => 'Biography section'])->save();
  }

  /**
   * Returns the content summary items of a paragraph.
   */
  private function summaryContent(Paragraph $paragraph): array {
    return array_map('strval', $paragraph->getSummaryItems()['content']);
  }

  /**
   * A section's summary is its title, without the body.
   */
  public function testSummaryIsTitle(): void {
    $section = Paragraph::create([
      'type' => 'formatted_text_with_title',
      'field_title' => '  Childhood  ',
      'field_body' => '<p>Born in Haniumalu.</p>',
    ]);
    $this->assertSame(['Childhood'], $this->summaryContent($section));
  }

  /**
   * A section without a title has no summary.
   */
  public function testSectionWithoutTitleHasNoSummary(): void {
    $section = Paragraph::create([
      'type' => 'formatted_text_with_title',
      'field_body' => '<p>Born in Haniumalu.</p>',
    ]);
    $this->assertSame([], $this->summaryContent($section));
  }

  /**
   * A long title is cut to 150 characters, ellipsis included.
   */
  public function testTitleIsTruncated(): void {
    $section = Paragraph::create([
      'type' => 'formatted_text_with_title',
      'field_title' => str_repeat('a', 200),
    ]);
    $this->assertSame([str_repeat('a', 149) . '…'], $this->summaryContent($section));
  }

}
