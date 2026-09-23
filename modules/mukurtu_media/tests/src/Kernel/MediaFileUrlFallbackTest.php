<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;

/**
 * Tests that files with unusable stream wrappers degrade instead of fataling.
 *
 * File::createFileUrl() throws InvalidStreamWrapperException when a file URI
 * carries a scheme with no registered stream wrapper. The theme used to call
 * it unguarded, so one such file aborted the whole page render - a browse
 * listing died on its first bad thumbnail. MukurtuV4FileUrl::fromFile() now
 * absorbs that, and callers fall back or drop the affordance.
 *
 * private:// is the realistic case rather than a contrivance: Drupal registers
 * that wrapper only while compiling the container, gated on file_private_path,
 * so a site that gains the setting after its container was built keeps
 * throwing until caches are rebuilt.
 *
 * @see \MukurtuV4FileUrl
 * @see \MukurtuV4AudioHelper::fallbackThumbnailUrl()
 * @see mukurtu_v4_preprocess_media()
 */
#[Group('mukurtu_media')]
class MediaFileUrlFallbackTest extends KernelTestBase {

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
   *
   * mukurtu_media.schema.yml declares mukurtu_thumbnail.settings' keys under a
   * nested 'default_thumbnail' sequence while ThumbnailSettingsForm reads and
   * writes flat per-bundle keys. Pre-existing mismatch, same escape hatch as
   * ThumbnailSettingsFormPreviewTest.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Collects log records written during a test.
   */
  protected object $logger;

  /**
   * The bundled icon MukurtuV4AudioHelper falls back to last.
   */
  protected const BUNDLED_AUDIO_ICON = '/profiles/mukurtu/themes/mukurtu_v4/images/audio.png';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installConfig([
      'field',
      'system',
      'image',
      'file',
      'media',
      'filter',
    ]);

    // The real audio bundle definition, so the Audio bundle class and its
    // field_thumbnail base field are in play. Taken from mukurtu_core's
    // config/install rather than installing all of mukurtu_core's config,
    // which drags in content types this test has no use for. Matches
    // ThumbnailAltTextAutofillTest.
    $mukurtu_core_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $definition = Yaml::decode(file_get_contents("$mukurtu_core_path/config/install/media.type.audio.yml"));
    MediaType::create($definition)->save();

    // A minimal file-source bundle for the preprocess assertions. Mukurtu's
    // own source fields are registered programmatically via
    // hook_entity_field_storage_info(); a plain field on a throwaway bundle
    // keeps this test away from that machinery. The storage has to exist
    // before the media schema is installed so it lands in the initial field
    // map - see MukurtuMediaTestBase for the same trap.
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_test_source',
      'type' => 'file',
    ])->save();
    MediaType::create([
      'id' => 'test_file',
      'label' => 'Test file',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_test_source'],
    ])->save();

    $this->installEntitySchema('media');

    if (!FieldConfig::loadByName('media', 'test_file', 'field_media_test_source')) {
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('media', 'field_media_test_source'),
        'bundle' => 'test_file',
        'label' => 'Test source',
      ])->save();
    }
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    User::create(['uid' => 1, 'name' => 'uid1-placeholder', 'status' => 1])->save();

    // The functions and helper classes under test live in the theme, so load
    // it directly rather than installing the theme. This also pulls in
    // includes/AudioHelper.php and includes/FileUrlHelper.php, which the
    // .theme file requires at the top.
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';

    // PHPUnit keeps statics alive between test methods in one process, so the
    // dedupe state has to be cleared or the log assertions read stale results.
    \MukurtuV4FileUrl::resetLoggedSchemes();

    $this->logger = new class extends AbstractLogger {

      /**
       * Every record written, in order.
       *
       * @var array
       */
      public array $records = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
      }

    };
    $this->container->get('logger.factory')->addLogger($this->logger);
  }

  /**
   * Negative control for every other assertion in this class.
   *
   * These tests only prove anything while private:// is genuinely unregistered
   * here. KernelTestBase never sets file_private_path (only
   * FunctionalTestSetupTrait does), so it is not. If that ever changes, the
   * rest of this class would quietly pass while testing nothing; this fails
   * loudly instead.
   */
  public function testPrivateSchemeIsUnregisteredInKernelTests(): void {
    $this->assertFalse(
      \Drupal::service('stream_wrapper_manager')->isValidScheme('private'),
      'private:// must be unregistered for these tests to exercise the failure path.'
    );
  }

  /**
   * An unusable scheme yields NULL rather than an exception.
   */
  public function testFromFileReturnsNullForUnregisteredScheme(): void {
    $this->assertNull(\MukurtuV4FileUrl::fromFile($this->createFile('private://locked.png')));
  }

  /**
   * A usable scheme still produces a URL.
   */
  public function testFromFileStillResolvesUsableSchemes(): void {
    $url = \MukurtuV4FileUrl::fromFile($this->createFile('public://open.png'));

    $this->assertIsString($url);
    $this->assertStringContainsString('open.png', $url);
  }

  /**
   * A NULL file is accepted, so callers can pass ->entity straight through.
   */
  public function testFromFileAcceptsNull(): void {
    $this->assertNull(\MukurtuV4FileUrl::fromFile(NULL));
  }

  /**
   * One broken scheme logs once, however many files share it.
   *
   * A browse listing can hold hundreds of files behind a single missing
   * wrapper; one row each would bury the log.
   */
  public function testRepeatedFailuresOnOneSchemeLogOnce(): void {
    foreach (['a.png', 'b.png', 'c.png'] as $name) {
      \MukurtuV4FileUrl::fromFile($this->createFile("private://$name"));
    }

    $warnings = array_filter(
      $this->logger->records,
      fn(array $record): bool => ($record['context']['@scheme'] ?? '') === 'private'
    );
    $this->assertCount(1, $warnings);

    $warning = reset($warnings);
    $this->assertSame('private://a.png', $warning['context']['@uri']);
  }

  /**
   * preprocess_media leaves media_url unset when no URL can be built.
   *
   * Unset is the same state the templates already handle for a media item with
   * no file at all, so they suppress the download link rather than rendering
   * an empty href.
   */
  public function testPreprocessMediaOmitsMediaUrlForUnusableScheme(): void {
    $variables = $this->preprocessMediaWithSourceFile('private://recording.mp3');

    $this->assertArrayNotHasKey('media_url', $variables);
  }

  /**
   * preprocess_media still sets media_url for a usable scheme.
   */
  public function testPreprocessMediaSetsMediaUrlForUsableScheme(): void {
    $variables = $this->preprocessMediaWithSourceFile('public://recording.mp3');

    $this->assertArrayHasKey('media_url', $variables);
    $this->assertStringContainsString('recording.mp3', $variables['media_url']);
  }

  /**
   * An unreachable uploaded thumbnail falls through to the admin default.
   *
   * This is the tier that used to hand back a URL it could not build.
   */
  public function testAudioThumbnailFallsThroughToConfiguredDefault(): void {
    $default = $this->createFile('public://site-default.png');
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('audio', [$default->id()])
      ->save();

    $media = $this->createAudioWithThumbnail('private://uploaded.png');

    $variables = [];
    $url = \MukurtuV4AudioHelper::fallbackThumbnailUrl($media, $variables);

    $this->assertStringContainsString('site-default.png', $url);
  }

  /**
   * With every tier unreachable, the bundled icon is used.
   */
  public function testAudioThumbnailFallsThroughToBundledIcon(): void {
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('audio', [$this->createFile('private://site-default.png')->id()])
      ->save();

    $media = $this->createAudioWithThumbnail('private://uploaded.png');

    $variables = [];

    $this->assertSame(self::BUNDLED_AUDIO_ICON, \MukurtuV4AudioHelper::fallbackThumbnailUrl($media, $variables));
  }

  /**
   * prepare_view strips a source file that no formatter could render.
   *
   * Core formatters build their own URLs, and image_url does it eagerly while
   * the display is still being assembled, so the element has to be gone before
   * that point. Preprocess and templates are both too late.
   */
  public function testPrepareViewDropsUnresolvableSourceFile(): void {
    $media = $this->createSavedTestFileMedia('private://locked.mp3');

    mukurtu_media_entity_prepare_view('media', [$media], [], 'default');

    $this->assertTrue($media->get('field_media_test_source')->isEmpty());
    $this->assertTrue(mukurtu_media_source_is_unavailable($media));
  }

  /**
   * A resolvable source file is left alone.
   */
  public function testPrepareViewKeepsResolvableSourceFile(): void {
    $media = $this->createSavedTestFileMedia('public://open.mp3');

    mukurtu_media_entity_prepare_view('media', [$media], [], 'default');

    $this->assertFalse($media->get('field_media_test_source')->isEmpty());
    $this->assertFalse(mukurtu_media_source_is_unavailable($media));
  }

  /**
   * Other entity types are ignored.
   */
  public function testPrepareViewIgnoresOtherEntityTypes(): void {
    $media = $this->createSavedTestFileMedia('private://locked.mp3');

    mukurtu_media_entity_prepare_view('node', [$media], [], 'default');

    $this->assertFalse($media->get('field_media_test_source')->isEmpty());
  }

  /**
   * A media item whose source is fine is not reported as unavailable.
   *
   * Guards the distinction the templates depend on: an item missing only its
   * thumbnail still has something to show, and must not be replaced with the
   * "not available" notice.
   */
  public function testUnresolvableThumbnailAloneIsNotUnavailable(): void {
    $media = $this->createSavedTestFileMedia('public://open.mp3');
    $media->set('thumbnail', ['target_id' => $this->createFile('private://thumb.png')->id()]);

    mukurtu_media_entity_prepare_view('media', [$media], [], 'default');

    $this->assertTrue($media->get('thumbnail')->isEmpty(), 'The unusable thumbnail is dropped.');
    $this->assertFalse($media->get('field_media_test_source')->isEmpty(), 'The usable source file is kept.');
    $this->assertFalse(mukurtu_media_source_is_unavailable($media));
  }

  /**
   * Creates and saves a test_file media item carrying the given file.
   */
  protected function createSavedTestFileMedia(string $uri): Media {
    $media = Media::create([
      'bundle' => 'test_file',
      'name' => 'Prepare view test',
      'field_media_test_source' => ['target_id' => $this->createFile($uri)->id()],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Creates a saved file entity at the given URI.
   *
   * No bytes are written: URL generation never opens the file, and the
   * private:// cases could not be written anyway.
   */
  protected function createFile(string $uri): File {
    $file = File::create([
      'uri' => $uri,
      'filename' => basename($uri),
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Builds an unsaved audio media item carrying the given thumbnail URI.
   */
  protected function createAudioWithThumbnail(string $uri): Media {
    return Media::create([
      'bundle' => 'audio',
      'name' => 'Audio fallback test',
      'field_thumbnail' => ['target_id' => $this->createFile($uri)->id()],
    ]);
  }

  /**
   * Runs mukurtu_v4_preprocess_media() over media carrying the given file.
   *
   * The view mode is deliberately not one of the thumbnail view modes, so
   * these assertions isolate media_url from the fallback-thumbnail branch.
   *
   * @return array
   *   The preprocessed variables.
   */
  protected function preprocessMediaWithSourceFile(string $uri): array {
    $media = Media::create([
      'bundle' => 'test_file',
      'name' => 'Source file test',
      'field_media_test_source' => ['target_id' => $this->createFile($uri)->id()],
    ]);

    $variables = [
      'media' => $media,
      'elements' => ['#view_mode' => 'default'],
    ];
    mukurtu_v4_preprocess_media($variables);

    return $variables;
  }

}
