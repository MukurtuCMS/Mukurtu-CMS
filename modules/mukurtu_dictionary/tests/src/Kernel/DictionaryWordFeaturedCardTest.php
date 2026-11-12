<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the media on a dictionary word's card (#2571).
 *
 * Related content and featured content show items as cards in the featured
 * view mode. A word's card shows its recording, then its thumbnail, then a
 * generic graphic.
 *
 * @see _mukurtu_v4_dictionary_word_card_media()
 */
#[Group('mukurtu_dictionary')]
class DictionaryWordFeaturedCardTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   *
   * The mukurtu_media module gives the audio and image media their bundle
   * classes and fields, and its services need the other three. The card
   * template uses twig_tweak's add_suggestion filter.
   */
  protected static $modules = [
    'flag',
    'key',
    'media_entity_soundcloud',
    'mukurtu_media',
    'twig_tweak',
  ];

  /**
   * A recording the viewer can hear.
   */
  protected Media $recording;

  /**
   * An image the viewer can see.
   */
  protected Media $image;

  /**
   * An image in a strict protocol the viewer is not a member of.
   */
  protected Media $restrictedImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Same media setup as MukurtuMediaTestBase: one shared file source field.
    $this->installConfig(['media']);
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_test_source',
      'type' => 'file',
    ])->save();
    $this->installEntitySchema('media');
    foreach (['audio', 'image'] as $bundle) {
      MediaType::create([
        'id' => $bundle,
        'label' => ucfirst($bundle),
        'source' => 'file',
        'source_configuration' => ['source_field' => 'field_media_test_source'],
      ])->save();
      if (!FieldConfig::loadByName('media', $bundle, 'field_media_test_source')) {
        FieldConfig::create([
          'field_storage' => FieldStorageConfig::loadByName('media', 'field_media_test_source'),
          'bundle' => $bundle,
          'label' => 'Test Source',
        ])->save();
      }
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    foreach (['media_tag', 'people'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }

    $this->recording = $this->createMedia('audio', 'Recording');
    $this->image = $this->createMedia('image', 'Thumbnail');

    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $strict->addMember($this->currentUser, ['protocol_steward']);
    $this->restrictedImage = $this->createMedia('image', 'Restricted thumbnail', $strict);

    // View cards as someone outside the strict protocol.
    $viewer = User::create(['name' => 'viewer']);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);

    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    require_once $this->root . '/' . $theme_path . '/mukurtu_v4.theme';
  }

  /**
   * Creates a media item in the given protocol, or the open one.
   */
  private function createMedia(string $bundle, string $name, ?Protocol $protocol = NULL): Media {
    $media = Media::create(['bundle' => $bundle, 'name' => $name, 'uid' => $this->currentUser->id()]);
    $media->setSharingSetting('any');
    $media->setProtocols([$protocol ?? $this->protocol]);
    $media->save();
    return $media;
  }

  /**
   * Creates a word with the given media fields.
   */
  private function createWord(array $values): DictionaryWord {
    $word = $this->buildDictionaryWord('aloha');
    foreach ($values as $field => $media) {
      $word->set($field, [$media->id()]);
    }
    $word->save();
    return Node::load($word->id());
  }

  /**
   * Picks the card media for a word.
   */
  private function cardMedia(DictionaryWord $word): array {
    return _mukurtu_v4_dictionary_word_card_media($word);
  }

  /**
   * A word with both shows its thumbnail behind its recording.
   */
  public function testRecordingAndThumbnail(): void {
    $card = $this->cardMedia($this->createWord([
      'field_recording' => $this->recording,
      'field_thumbnail' => $this->image,
    ]));

    $this->assertSame($this->recording->id(), $card['#media']->id());
    $this->assertSame($this->image->id(), $card['#mukurtu_card_image']['#media']->id());
    // The recording could belong to another word with another thumbnail.
    $this->assertContains('card-image:' . $this->image->id(), $card['#cache']['keys']);
  }

  /**
   * A recording alone keeps its own image.
   */
  public function testRecordingOnly(): void {
    $card = $this->cardMedia($this->createWord(['field_recording' => $this->recording]));
    $this->assertSame($this->recording->id(), $card['#media']->id());
    $this->assertArrayNotHasKey('#mukurtu_card_image', $card);
  }

  /**
   * A thumbnail alone is the card's image.
   */
  public function testThumbnailOnly(): void {
    $card = $this->cardMedia($this->createWord(['field_thumbnail' => $this->image]));
    $this->assertSame($this->image->id(), $card['#media']->id());
  }

  /**
   * Media assets are not used: the card falls back to the generic graphic.
   */
  public function testMediaAssetsAreIgnored(): void {
    $card = $this->cardMedia($this->createWord(['field_media_assets' => $this->image]));
    $this->assertSame('image', $card['#theme']);
    $this->assertStringEndsWith('/images/no-thumbnail.png', $card['#uri']);
  }

  /**
   * A thumbnail the viewer can't see is skipped, and the card notes why.
   */
  public function testRestrictedThumbnail(): void {
    $card = $this->cardMedia($this->createWord([
      'field_recording' => $this->recording,
      'field_thumbnail' => $this->restrictedImage,
    ]));
    $this->assertSame($this->recording->id(), $card['#media']->id());
    $this->assertArrayNotHasKey('#mukurtu_card_image', $card);
    // Access depends on protocol membership, so the card must vary by it.
    $this->assertNotEmpty($card['#cache']['contexts']);
  }

  /**
   * Sets one of the site's placeholder images.
   */
  private function setPlaceholder(string $key, string $uri): void {
    $this->installEntitySchema('file');
    file_put_contents($uri, 'png');
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    $this->config('mukurtu_media.settings')->set($key, [(int) $file->id()])->save();
  }

  /**
   * The site's "No Media Placeholder Image" beats the theme's graphic.
   */
  public function testNoMediaPlaceholder(): void {
    $this->setPlaceholder('no_media_placeholder', 'public://no-media.png');
    $card = $this->cardMedia($this->createWord([]));
    $this->assertSame('public://no-media.png', $card['#uri']);
    $this->assertSame('No media available', (string) $card['#alt']);
    $this->assertContains('config:mukurtu_media.settings', $card['#cache']['tags']);
  }

  /**
   * Media the viewer can't see gets the restricted placeholder.
   */
  public function testRestrictedPlaceholder(): void {
    $this->setPlaceholder('restricted_media_placeholder', 'public://restricted.png');
    $card = $this->cardMedia($this->createWord(['field_thumbnail' => $this->restrictedImage]));
    $this->assertSame('public://restricted.png', $card['#uri']);

    // A word with no media at all doesn't get it.
    $card = $this->cardMedia($this->createWord([]));
    $this->assertStringEndsWith('/images/no-thumbnail.png', $card['#uri']);
  }

  /**
   * Cards in related and featured content and on the browse pages pick it.
   */
  public function testPreprocessSetsCardMedia(): void {
    $word = $this->createWord(['field_recording' => $this->recording]);
    foreach (['featured', 'browse', 'grid_browse', 'map_browse'] as $view_mode) {
      $variables = ['node' => $word, 'view_mode' => $view_mode, 'content' => []];
      mukurtu_v4_preprocess_node($variables);
      $this->assertSame($this->recording->id(), $variables['card_media']['#media']->id(), $view_mode);
      // A player isn't wrapped in the card's link.
      $this->assertSame('audio', $variables['card_media_bundle'], $view_mode);
    }

    // The word's own page and the Dictionary list keep their own layouts.
    foreach (['full', 'teaser'] as $view_mode) {
      $variables = ['node' => $word, 'view_mode' => $view_mode, 'content' => []];
      mukurtu_v4_preprocess_node($variables);
      $this->assertArrayNotHasKey('card_media', $variables, $view_mode);
    }

    // A static image is linked like any other browse image.
    $variables = ['node' => $this->createWord([]), 'view_mode' => 'browse', 'content' => []];
    mukurtu_v4_preprocess_node($variables);
    $this->assertSame('image', $variables['card_media_bundle']);
  }

  /**
   * The browse cards print the word's pick, not its media assets.
   */
  public function testBrowseTemplatesUseCardMedia(): void {
    // The templates include a partial by the theme's @mukurtu_v4 namespace,
    // which only exists for an installed theme.
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    $loader = $this->container->get('twig.loader.filesystem');
    $loader->addPath($this->root . '/' . $theme_path . '/templates', 'mukurtu_v4');
    $loader->addPath($this->root . '/' . $theme_path, 'mukurtu_v4');
    $context = [
      'content_attributes' => new Attribute(),
      'title_attributes' => new Attribute(),
      'label' => 'aloha',
      'url' => '/dictionary-word/aloha',
      'content' => [
        'field_representative_media' => [self::marker('ASSET')],
        'field_recording' => [self::marker('RECORDING')],
      ],
      'card_media' => self::marker('CARD'),
      'card_media_bundle' => 'image',
    ];
    foreach (['browse', 'grid-browse', 'map-browse'] as $view_mode) {
      $html = $this->renderTemplate("content/node--dictionary-word--$view_mode.html.twig", $context);
      $this->assertStringContainsString('CARD.png', $html, $view_mode);
      $this->assertStringNotContainsString('ASSET.png', $html, $view_mode);
      $this->assertStringNotContainsString('RECORDING.png', $html, $view_mode);
      // A static image links to the word.
      $this->assertMatchesRegularExpression('#<a href="/dictionary-word/aloha" aria-hidden="true" tabindex="-1">\s*<img[^>]*CARD\.png#', $html, $view_mode);
    }
  }

  /**
   * A warned thumbnail on a /dictionary teaser gets the card overlay (#1301).
   */
  public function testTeaserWrapsWarnedThumbnail(): void {
    // The teaser includes an icon from the active theme's directory. The
    // theme is not installed, so initTheme() would fall back to "core". An
    // empty engine skips loading a theme extension we don't have; Twig still
    // loads, since core always includes it.
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    $this->container->get('theme.manager')->setActiveTheme(new ActiveTheme([
      'name' => 'mukurtu_v4',
      'path' => $theme_path,
      'engine' => '',
    ]));
    $context = [
      'content_attributes' => new Attribute(),
      'title_attributes' => new Attribute(),
      'title_prefix' => [],
      'title_suffix' => [],
      'label' => 'aloha',
      'url' => '/dictionary-word/aloha',
    ];

    $warned = ['#markup' => '<div class="media"><div class="mukurtu-content-warnings">Warning</div>THUMBNAIL</div>'];
    $html = $this->renderTemplate('content/node--dictionary-word--teaser.html.twig', $context + ['content' => ['field_thumbnail' => $warned]]);
    $this->assertMatchesRegularExpression('#<div class="card-media--content-warning" data-mukurtu-card-url="/dictionary-word/aloha">\s*<div class="media"><div class="mukurtu-content-warnings">#', $html);

    $plain = ['#markup' => '<div class="media">THUMBNAIL</div>'];
    $html = $this->renderTemplate('content/node--dictionary-word--teaser.html.twig', $context + ['content' => ['field_thumbnail' => $plain]]);
    $this->assertStringContainsString('THUMBNAIL', $html);
    $this->assertStringNotContainsString('card-media--content-warning', $html);
    // Unwarned thumbnails stay unlinked, as before.
    $this->assertMatchesRegularExpression('#<div class="dictionary-word__teaser-thumbnail">\s*<div class="media">THUMBNAIL#', $html);
  }

  /**
   * Renders a theme template with the given variables.
   */
  private function renderTemplate(string $path, array $context): string {
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    $build = [
      '#type' => 'inline_template',
      '#template' => file_get_contents($this->root . '/' . $theme_path . '/templates/' . $path),
      '#context' => $context + ['attributes' => new Attribute()],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * A themed placeholder named after a marker; add_suggestion needs #theme.
   */
  private static function marker(string $name): array {
    return ['#theme' => 'image', '#uri' => "public://$name.png", '#alt' => ''];
  }

  /**
   * The card template uses the word's pick over the fields it would choose.
   */
  public function testCardTemplateUsesCardMedia(): void {
    $context = [
      'title_attributes' => new Attribute(),
      'node' => ['type' => ['entity' => ['label' => 'Dictionary Word']]],
      'label' => self::marker('label'),
      'url' => '/dictionary-word/aloha',
      'content' => ['field_recording' => [self::marker('RECORDING')]],
    ];
    $html = $this->renderTemplate('content/node--featured.html.twig', $context + ['card_media' => self::marker('CARD')]);
    $this->assertStringContainsString('CARD.png', $html);
    $this->assertStringNotContainsString('RECORDING.png', $html);

    // Other cards are unchanged.
    $html = $this->renderTemplate('content/node--featured.html.twig', $context);
    $this->assertStringContainsString('RECORDING.png', $html);
  }

  /**
   * The audio card shows the word's thumbnail in place of its own image.
   */
  public function testAudioTemplateUsesCardImage(): void {
    $context = [
      'name' => 'Recording',
      'bundle' => 'audio',
      'audio_fallback_thumbnail_url' => '/audio.png',
      'content' => ['field_thumbnail' => ['#items' => [1], self::marker('AUDIO_THUMBNAIL')]],
    ];
    foreach ([FALSE, TRUE] as $skip_player) {
      $html = $this->renderTemplate('media/media--audio--teaser-featured.html.twig', $context + [
        'skip_player' => $skip_player,
        'card_image' => self::marker('WORD_THUMBNAIL'),
      ]);
      $this->assertStringContainsString('WORD_THUMBNAIL.png', $html);
      $this->assertStringNotContainsString('AUDIO_THUMBNAIL.png', $html);
    }

    // Without one, the recording keeps its own image.
    $html = $this->renderTemplate('media/media--audio--teaser-featured.html.twig', $context + ['skip_player' => FALSE]);
    $this->assertStringContainsString('AUDIO_THUMBNAIL.png', $html);
  }

}
