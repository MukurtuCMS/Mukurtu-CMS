<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the multiple media icon on dictionary word browse cards (#1806).
 *
 * A word's card plays its first recording, so the icon shows when the viewer
 * can see two or more items across its recordings and media assets.
 *
 * @see _mukurtu_v4_has_multiple_media()
 * @see templates/content/_card-media.html.twig
 */
#[Group('mukurtu_dictionary')]
class DictionaryWordMultipleMediaTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   *
   * The mukurtu_media module gives the audio and image media their bundle
   * classes and fields, and its services need the other three. The card
   * templates use twig_tweak's add_suggestion filter.
   */
  protected static $modules = [
    'flag',
    'key',
    'media_entity_soundcloud',
    'mukurtu_media',
    'twig_tweak',
  ];

  /**
   * Two recordings the viewer can hear.
   *
   * @var \Drupal\media\Entity\Media[]
   */
  protected array $recordings;

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

    $this->recordings = [
      $this->createMedia('audio', 'Recording 1'),
      $this->createMedia('audio', 'Recording 2'),
    ];
    $this->image = $this->createMedia('image', 'Image');

    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $strict->addMember($this->currentUser, ['protocol_steward']);
    $this->restrictedImage = $this->createMedia('image', 'Restricted image', $strict);

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
   * Creates a word with the given media in each field.
   *
   * @param \Drupal\media\Entity\Media[][] $values
   *   Media items, keyed by field name.
   */
  private function createWord(array $values): DictionaryWord {
    $word = $this->buildDictionaryWord('aloha');
    foreach ($values as $field => $media) {
      $word->set($field, array_map(fn (Media $item) => $item->id(), $media));
    }
    $word->save();
    return Node::load($word->id());
  }

  /**
   * Runs the theme's node preprocess for a word's card.
   */
  private function preprocess(DictionaryWord $word, string $view_mode): array {
    $variables = ['node' => $word, 'view_mode' => $view_mode, 'content' => []];
    mukurtu_v4_preprocess_node($variables);
    return $variables;
  }

  /**
   * Counts recordings and media assets together, viewable items only.
   */
  public function testCountsViewableRecordingsAndAssets(): void {
    [$first, $second] = $this->recordings;
    $cases = [
      'one recording' => [
        ['field_recording' => [$first]],
        FALSE,
      ],
      'two recordings' => [
        ['field_recording' => [$first, $second]],
        TRUE,
      ],
      'a recording and an asset' => [
        ['field_recording' => [$first], 'field_media_assets' => [$this->image]],
        TRUE,
      ],
      'a recording and a restricted asset' => [
        ['field_recording' => [$first], 'field_media_assets' => [$this->restrictedImage]],
        FALSE,
      ],
      'the same item in both fields' => [
        ['field_recording' => [$first], 'field_media_assets' => [$first]],
        FALSE,
      ],
      'two assets and no recording' => [
        ['field_media_assets' => [$this->image, $first]],
        TRUE,
      ],
    ];
    foreach ($cases as $label => [$values, $expected]) {
      $word = $this->createWord($values);
      $this->assertSame($expected, $this->preprocess($word, 'browse')['has_multiple_media'], $label);
    }
  }

  /**
   * A restricted item varies the card's cache by the viewer's access.
   */
  public function testRestrictedMediaVariesTheCache(): void {
    $word = $this->createWord([
      'field_recording' => [$this->recordings[0]],
      'field_media_assets' => [$this->restrictedImage],
    ]);
    $cache = CacheableMetadata::createFromRenderArray($this->preprocess($word, 'browse')['multiple_media_cache']);
    $this->assertNotEmpty($cache->getCacheContexts());
    // Changing either media item can change the answer.
    $this->assertContains('media:' . $this->restrictedImage->id(), $cache->getCacheTags());
    $this->assertContains('media:' . $this->recordings[0]->id(), $cache->getCacheTags());
  }

  /**
   * Only the browse cards get the icon.
   */
  public function testOnlyBrowseCards(): void {
    $word = $this->createWord(['field_recording' => $this->recordings]);
    foreach (['browse', 'grid_browse', 'map_browse'] as $view_mode) {
      $this->assertTrue($this->preprocess($word, $view_mode)['has_multiple_media'], $view_mode);
    }
    foreach (['full', 'teaser', 'featured'] as $view_mode) {
      $this->assertArrayNotHasKey('has_multiple_media', $this->preprocess($word, $view_mode), $view_mode);
    }
  }

  /**
   * The card shows the icon, with text for screen readers.
   */
  public function testBrowseTemplatesShowTheIcon(): void {
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
      'content' => [],
      'card_media' => ['#theme' => 'image', '#uri' => 'public://CARD.png', '#alt' => ''],
      'card_media_bundle' => 'image',
      'multiple_media_cache' => [],
    ];
    foreach (['browse', 'grid-browse', 'map-browse'] as $view_mode) {
      $html = $this->renderTemplate("content/node--dictionary-word--$view_mode.html.twig", $context + ['has_multiple_media' => TRUE]);
      // After the link, which is hidden from screen readers, so the text is
      // announced.
      $this->assertMatchesRegularExpression('#CARD\.png[^>]*>\s*</a>\s*<span class="card-media__multiple">\s*<svg[^>]*aria-hidden="true"#', $html, $view_mode);
      $this->assertStringContainsString('<span class="visually-hidden">Includes multiple media assets</span>', $html, $view_mode);

      $html = $this->renderTemplate("content/node--dictionary-word--$view_mode.html.twig", $context + ['has_multiple_media' => FALSE]);
      $this->assertStringNotContainsString('card-media__multiple', $html, $view_mode);
    }
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

}
