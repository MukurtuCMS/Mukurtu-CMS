<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40042(), which points the sample sentence
 * recording field at the same media view modes used by the dictionary
 * word's own Recording field, so it renders with the "Download audio"
 * button instead of a plain download icon (#2019).
 *
 * @see mukurtu_dictionary_update_40042()
 */
#[Group('mukurtu_dictionary')]
class SampleSentenceRecordingDisplayUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The hook writes a partial view-display config fixture, not full data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary');
    require_once $module_path . '/mukurtu_dictionary.install';
  }

  /**
   * The hook sets the view mode and link setting on both the default and
   * teaser sample sentence displays.
   */
  public function testUpdateSetsViewModeOnBothDisplays(): void {
    $displays = [
      'core.entity_view_display.paragraph.sample_sentence.default',
      'core.entity_view_display.paragraph.sample_sentence.teaser',
    ];
    foreach ($displays as $display_id) {
      \Drupal::configFactory()->getEditable($display_id)
        ->setData([
          'content' => [
            'field_sentence_recording' => [
              'settings' => [
                'view_mode' => 'default',
                'link' => TRUE,
              ],
            ],
          ],
        ])
        ->save();
    }

    mukurtu_dictionary_update_40042();

    $default_settings = \Drupal::config('core.entity_view_display.paragraph.sample_sentence.default')
      ->get('content.field_sentence_recording.settings');
    $this->assertSame('audio_for_dictionary_word', $default_settings['view_mode']);
    $this->assertFalse($default_settings['link']);

    $teaser_settings = \Drupal::config('core.entity_view_display.paragraph.sample_sentence.teaser')
      ->get('content.field_sentence_recording.settings');
    $this->assertSame('browse', $teaser_settings['view_mode']);
    $this->assertFalse($teaser_settings['link']);
  }

  /**
   * The update hook skips a display that doesn't exist, and still updates
   * the other one.
   */
  public function testUpdateSkipsMissingDisplay(): void {
    \Drupal::configFactory()->getEditable('core.entity_view_display.paragraph.sample_sentence.default')
      ->setData([
        'content' => [
          'field_sentence_recording' => [
            'settings' => [
              'view_mode' => 'default',
              'link' => TRUE,
            ],
          ],
        ],
      ])
      ->save();

    $this->assertTrue(\Drupal::config('core.entity_view_display.paragraph.sample_sentence.teaser')->isNew());

    mukurtu_dictionary_update_40042();

    $this->assertTrue(\Drupal::config('core.entity_view_display.paragraph.sample_sentence.teaser')->isNew());
    $this->assertSame(
      'audio_for_dictionary_word',
      \Drupal::config('core.entity_view_display.paragraph.sample_sentence.default')
        ->get('content.field_sentence_recording.settings.view_mode')
    );
  }

}
