<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the collapsed summaries of Word Entry and Sample Sentence paragraphs.
 *
 * Paragraphs only summarizes configurable fields, and every field on these
 * bundles is code-defined, so without our overrides a collapsed item shows
 * only its type label.
 */
#[Group('mukurtu_dictionary')]
class DictionaryParagraphSummaryTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('media');
    $media_type = MediaType::create(['id' => 'audio', 'label' => 'Audio', 'source' => 'audio_file']);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $media_type->set('source_configuration', ['source_field' => $source_field->getName()])->save();

    $role = Role::load('authenticated');
    $role->grantPermission('view media');
    $role->save();
  }

  /**
   * Returns the content summary items of a paragraph.
   */
  private function summaryContent(Paragraph $paragraph): array {
    return array_map('strval', $paragraph->getSummaryItems()['content']);
  }

  /**
   * A Word Entry's summary is its term.
   */
  public function testWordEntrySummaryIsTerm(): void {
    $entry = Paragraph::create([
      'type' => 'dictionary_word_entry',
      'field_word_entry_term' => '  kʷúkʷi  ',
      'field_translation' => ['apple'],
    ]);
    $this->assertSame(['kʷúkʷi'], $this->summaryContent($entry));
  }

  /**
   * A Word Entry without a term has no summary.
   */
  public function testWordEntryWithoutTermHasNoSummary(): void {
    $entry = Paragraph::create([
      'type' => 'dictionary_word_entry',
      'field_translation' => ['apple'],
    ]);
    $this->assertSame([], $this->summaryContent($entry));
  }

  /**
   * A long term is truncated.
   */
  public function testWordEntryTermIsTruncated(): void {
    $entry = Paragraph::create([
      'type' => 'dictionary_word_entry',
      'field_word_entry_term' => str_repeat('a', 200),
    ]);
    $this->assertSame([str_repeat('a', 150)], $this->summaryContent($entry));
  }

  /**
   * A Sample Sentence's summary is its text, with markup stripped.
   */
  public function testSampleSentenceSummaryIsText(): void {
    $sentence = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence' => '<p>The <strong>apple</strong> is red &amp; ripe.</p>',
    ]);
    $this->assertSame(['The apple is red & ripe.'], $this->summaryContent($sentence));
  }

  /**
   * A long sentence is truncated.
   */
  public function testSampleSentenceIsTruncated(): void {
    $sentence = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence' => str_repeat('b', 300),
    ]);
    $this->assertSame([str_repeat('b', 150)], $this->summaryContent($sentence));
  }

  /**
   * An audio-only Sample Sentence falls back to the recording's name.
   */
  public function testRecordingOnlySampleSentenceUsesMediaName(): void {
    $media = Media::create([
      'bundle' => 'audio',
      'name' => 'Elder reading',
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $media->save();

    $sentence = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence_recording' => $media->id(),
    ]);
    $this->assertSame(['Elder reading'], $this->summaryContent($sentence));
  }

  /**
   * The recording's name is hidden from someone who can't view it.
   */
  public function testRecordingNameRespectsAccess(): void {
    $media = Media::create([
      'bundle' => 'audio',
      'name' => 'Restricted reading',
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $media->save();

    $role = Role::load('authenticated');
    $role->revokePermission('view media');
    $role->save();

    // The base class's current user is uid 1, which bypasses access checks.
    $viewer = User::create(['name' => $this->randomMachineName()]);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);

    $sentence = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence_recording' => $media->id(),
    ]);
    $this->assertSame([], $this->summaryContent($sentence));
  }

}
