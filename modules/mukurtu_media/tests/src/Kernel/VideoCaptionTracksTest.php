<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests caption and subtitle tracks on uploaded video.
 *
 * @see \Drupal\mukurtu_media\Plugin\Field\FieldType\VideoTrackItem
 * @see \Drupal\mukurtu_media\Plugin\Field\FieldWidget\VideoTrackWidget
 * @see \Drupal\mukurtu_media\Plugin\Field\FieldFormatter\VideoWithTracksFormatter
 */
#[Group('mukurtu_media')]
class VideoCaptionTracksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'key',
    'language',
    'leaflet',
    'media',
    'media_entity_soundcloud',
    'media_library',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    // The edit form's author autocomplete checks OG membership.
    $this->installEntitySchema('og_membership');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installConfig(['field', 'system', 'image', 'file', 'media', 'filter', 'language']);

    // The real video bundle, so the Video bundle class and its fields apply.
    $mukurtu_core_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $definition = Yaml::decode(file_get_contents("$mukurtu_core_path/config/install/media.type.video.yml"));
    MediaType::create($definition)->save();
    $this->installEntitySchema('media');

    // The Language vocabulary and its language code field (#2328).
    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'taxonomy_term',
      'field_name' => 'field_language_code',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'taxonomy_term',
      'bundle' => 'language',
      'field_name' => 'field_language_code',
    ])->save();

    // The uid 1 account bypasses access, so the widget's term query sees
    // every term.
    $admin = User::create(['uid' => 1, 'name' => 'admin', 'status' => 1]);
    $admin->save();
    \Drupal::currentUser()->setAccount($admin);
  }

  /**
   * Creates a Language vocabulary term.
   */
  protected function createLanguageTerm(string $name, string $code = ''): TermInterface {
    $term = Term::create(['vid' => 'language', 'name' => $name, 'field_language_code' => $code]);
    $term->save();
    return $term;
  }

  /**
   * Creates a file entity.
   */
  protected function createFile(string $filename): File {
    $file = File::create(['uri' => "public://$filename", 'filename' => $filename, 'status' => 1]);
    $file->save();
    return $file;
  }

  /**
   * Creates a video with the given tracks.
   *
   * @param array<int, array<string, mixed>> $tracks
   *   Track field values.
   */
  protected function createVideo(array $tracks): MediaInterface {
    $media = Media::create([
      'bundle' => 'video',
      'name' => 'Story',
      'field_media_video_file' => ['target_id' => $this->createFile('story.mp4')->id()],
      'field_media_video_tracks' => $tracks,
    ]);
    $media->save();
    return Media::load($media->id());
  }

  /**
   * Renders the video file with the track formatter and returns its tracks.
   *
   * @return array<int, array<string, string>>
   *   Each track's attributes, decoded, in document order.
   */
  protected function renderTracks(MediaInterface $media): array {
    $build = $media->get('field_media_video_file')->view([
      'type' => 'mukurtu_video_with_tracks',
      'label' => 'hidden',
      'settings' => ['controls' => TRUE],
    ]);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('<video', $html);

    $document = new \DOMDocument();
    @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $tracks = [];
    foreach ($document->getElementsByTagName('track') as $track) {
      $attributes = [];
      foreach ($track->attributes as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
      }
      $tracks[] = $attributes;
    }
    return $tracks;
  }

  /**
   * Each track renders with its kind, source, language code, and label.
   */
  public function testTracksRender(): void {
    $lilwat = $this->createLanguageTerm("Lil'wat", 'lil');
    $unknown = $this->createLanguageTerm('Unknown dialect');
    $captions = $this->createFile('lil.vtt');
    $subtitles = $this->createFile('dialect.vtt');

    $media = $this->createVideo([
      ['target_id' => $captions->id(), 'language_target_id' => $lilwat->id(), 'kind' => 'captions'],
      ['target_id' => $subtitles->id(), 'language_target_id' => $unknown->id(), 'kind' => 'subtitles'],
    ]);

    $tracks = $this->renderTracks($media);
    $this->assertCount(2, $tracks);

    $this->assertSame('captions', $tracks[0]['kind']);
    $this->assertSame('lil', $tracks[0]['srclang']);
    // Escaped once, not twice, so the apostrophe survives.
    $this->assertSame("Lil'wat (captions)", $tracks[0]['label']);
    $this->assertStringEndsWith('/lil.vtt', $tracks[0]['src']);

    $this->assertSame('subtitles', $tracks[1]['kind']);
    $this->assertArrayNotHasKey('srclang', $tracks[1], 'A term without a language code gives no srclang.');
    $this->assertSame('Unknown dialect', $tracks[1]['label']);
  }

  /**
   * A track whose file entity is gone is skipped rather than rendered empty.
   */
  public function testMissingFileIsSkipped(): void {
    $term = $this->createLanguageTerm('English', 'en');
    $kept = $this->createFile('en.vtt');
    $gone = $this->createFile('gone.vtt');

    $media = $this->createVideo([
      ['target_id' => $gone->id(), 'language_target_id' => $term->id(), 'kind' => 'captions'],
      ['target_id' => $kept->id(), 'language_target_id' => $term->id(), 'kind' => 'subtitles'],
    ]);
    $gone->delete();
    $media = Media::load($media->id());

    $tracks = $this->renderTracks($media);
    $this->assertCount(1, $tracks);
    $this->assertStringEndsWith('/en.vtt', $tracks[0]['src']);
  }

  /**
   * The track label uses the language term's translation for the viewer.
   */
  public function testTrackLabelIsTranslated(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $term = $this->createLanguageTerm('German', 'de');
    $term->addTranslation('fr', ['name' => 'Allemand'])->save();

    $media = $this->createVideo([
      ['target_id' => $this->createFile('de.vtt')->id(), 'language_target_id' => $term->id(), 'kind' => 'subtitles'],
    ]);

    \Drupal::service('language.default')->set(ConfigurableLanguage::load('fr'));
    \Drupal::languageManager()->reset();

    $tracks = $this->renderTracks($media);
    $this->assertSame('Allemand', $tracks[0]['label']);
    $this->assertSame('de', $tracks[0]['srclang']);
  }

  /**
   * A video with no tracks renders a plain video, as before.
   */
  public function testNoTracks(): void {
    $this->assertSame([], $this->renderTracks($this->createVideo([])));
  }

  /**
   * Saving normalizes an unknown kind and an empty language.
   */
  public function testItemNormalizesOnSave(): void {
    $media = $this->createVideo([
      ['target_id' => $this->createFile('a.vtt')->id(), 'language_target_id' => 0, 'kind' => 'chapters'],
      ['target_id' => $this->createFile('b.vtt')->id()],
    ]);

    $tracks = $media->get('field_media_video_tracks');
    $this->assertSame('captions', $tracks[0]->kind);
    $this->assertNull($tracks[0]->language_target_id);
    $this->assertSame('captions', $tracks[1]->kind);
  }

  /**
   * The edit form asks for each track's language and type.
   */
  public function testWidgetAddsLanguageAndType(): void {
    $term = $this->createLanguageTerm('English', 'en');
    $this->createLanguageTerm('Anishinaabemowin', 'oj');
    $media = $this->createVideo([
      ['target_id' => $this->createFile('en.vtt')->id(), 'language_target_id' => $term->id(), 'kind' => 'subtitles'],
    ]);

    // The shipped form display, trimmed to the components whose widgets this
    // test's modules provide. mukurtu_media's edit form alter expects the
    // thumbnail widget.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_media');
    $display = Yaml::decode(file_get_contents("$module_path/config/install/core.entity_form_display.media.video.default.yml"));
    $display['content'] = array_intersect_key($display['content'], array_flip([
      'field_media_video_file',
      'field_media_video_tracks',
      'field_thumbnail',
    ]));
    unset($display['dependencies']);
    EntityFormDisplay::create($display)->save();

    $form_object = \Drupal::entityTypeManager()->getFormObject('media', 'edit')->setEntity($media);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $item = $form['field_media_video_tracks']['widget'][0];

    $language = $item['language_target_id'];
    $this->assertSame('select', $language['#type']);
    $this->assertTrue($language['#required']);
    $this->assertSame('Select a language for this track.', (string) $language['#required_error']);
    $this->assertSame(['Anishinaabemowin', 'English'], array_values(array_map('strval', $language['#options'])));
    $this->assertEquals($term->id(), $language['#value']);

    $kind = $item['kind'];
    $this->assertSame(['captions', 'subtitles'], array_keys($kind['#options']));
    $this->assertSame('subtitles', $kind['#value']);
  }

}
