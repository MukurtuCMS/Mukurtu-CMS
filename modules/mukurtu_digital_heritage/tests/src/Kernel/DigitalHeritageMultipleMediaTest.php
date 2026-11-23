<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the multiple media icon on digital heritage browse cards (#1806).
 *
 * The icon shows when the viewer can see two or more media assets.
 *
 * @see _mukurtu_v4_has_multiple_media()
 */
#[Group('mukurtu_digital_heritage')]
class DigitalHeritageMultipleMediaTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   *
   * The mukurtu_media module gives the image media its bundle class and
   * fields, and its services need the other four.
   */
  protected static $modules = [
    'flag',
    'key',
    'media_entity_soundcloud',
    'media_library',
    'mukurtu_media',
  ];

  /**
   * Two images the viewer can see.
   *
   * @var \Drupal\media\Entity\Media[]
   */
  protected array $images;

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
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['media']);
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_test_source',
      'type' => 'file',
    ])->save();
    MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_test_source'],
    ])->save();
    if (!FieldConfig::loadByName('media', 'image', 'field_media_test_source')) {
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('media', 'field_media_test_source'),
        'bundle' => 'image',
        'label' => 'Test Source',
      ])->save();
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    foreach (['media_tag', 'people'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }

    $this->images = [
      $this->createImage('Image 1'),
      $this->createImage('Image 2'),
    ];

    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $strict->addMember($this->currentUser, ['protocol_steward']);
    $this->restrictedImage = $this->createImage('Restricted image', $strict);

    // View cards as someone outside the strict protocol.
    $viewer = User::create(['name' => 'viewer']);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);

    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    require_once $this->root . '/' . $theme_path . '/mukurtu_v4.theme';
  }

  /**
   * Creates an image in the given protocol, or the open one.
   */
  private function createImage(string $name, ?Protocol $protocol = NULL): Media {
    $media = Media::create(['bundle' => 'image', 'name' => $name, 'uid' => $this->currentUser->id()]);
    $media->setSharingSetting('any');
    $media->setProtocols([$protocol ?? $this->protocol]);
    $media->save();
    return $media;
  }

  /**
   * Creates an item with the given media assets.
   *
   * @param \Drupal\media\Entity\Media[] $media
   *   The media assets.
   */
  private function createItem(array $media): NodeInterface {
    $item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    $item->set('field_media_assets', array_map(fn (Media $m) => $m->id(), $media));
    $item->save();
    return Node::load($item->id());
  }

  /**
   * Runs the theme's node preprocess for an item's card.
   */
  private function preprocess(NodeInterface $item, string $view_mode): array {
    $variables = ['node' => $item, 'view_mode' => $view_mode, 'content' => []];
    mukurtu_v4_preprocess_node($variables);
    return $variables;
  }

  /**
   * Two viewable media assets show the icon on every browse card.
   */
  public function testTwoAssets(): void {
    $item = $this->createItem($this->images);
    foreach (['browse', 'grid_browse', 'map_browse'] as $view_mode) {
      $this->assertTrue($this->preprocess($item, $view_mode)['has_multiple_media'], $view_mode);
    }
    // Other displays are left alone.
    foreach (['full', 'teaser', 'featured'] as $view_mode) {
      $this->assertArrayNotHasKey('has_multiple_media', $this->preprocess($item, $view_mode), $view_mode);
    }
  }

  /**
   * One media asset, or one the viewer can see, shows no icon.
   */
  public function testOneViewableAsset(): void {
    $item = $this->createItem([$this->images[0]]);
    $this->assertFalse($this->preprocess($item, 'browse')['has_multiple_media']);

    // The restricted asset doesn't count, so the card doesn't hint at it.
    $item = $this->createItem([$this->images[0], $this->restrictedImage]);
    $this->assertFalse($this->preprocess($item, 'grid_browse')['has_multiple_media']);
  }

}
