<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_browse\EventSubscriber\MediaTypeIndexingItemsSubscriber;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that remote media bundles are indexed as Audio, Video and 3D Model.
 */
#[Group('mukurtu_browse')]
class MediaTypeIndexingItemsSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_browse',
    'search_api',
  ];

  /**
   * The index the test items belong to. Never saved.
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->index = Index::create(['id' => 'test_index']);
  }

  /**
   * Media bundles in, expected indexed values out.
   */
  public static function mediaTypeProvider(): array {
    return [
      'SoundCloud becomes Audio' => [['soundcloud'], ['audio']],
      'Remote video becomes Video' => [['remote_video'], ['video']],
      'Local audio is unchanged' => [['audio'], ['audio']],
      'Local video is unchanged' => [['video'], ['video']],
      'Image is unchanged' => [['image'], ['image']],
      'Document is unchanged' => [['document'], ['document']],
      'External embed stays separate' => [['external_embed'], ['external_embed']],
      'Audio and SoundCloud count once' => [['audio', 'soundcloud'], ['audio']],
      'Video and remote video count once' => [['remote_video', 'video', 'remote_video'], ['video']],
      'Mixed media keeps each kind once' => [
        ['soundcloud', 'image', 'remote_video', 'audio'],
        ['audio', 'image', 'video'],
      ],
    ];
  }

  /**
   * Tests the media type field on the database index.
   */
  #[DataProvider('mediaTypeProvider')]
  public function testMediaTypeMerged(array $bundles, array $expected): void {
    $item = $this->createItem([
      'media_type' => ['entity:node', 'field_media_assets:entity:bundle', $bundles],
    ]);

    $this->dispatch($item);

    $this->assertSame($expected, $item->getField('media_type')->getValues());
  }

  /**
   * Embeds, by embed code, in; expected indexed values out.
   */
  public static function embedProvider(): array {
    $sketchfab = '<iframe title="Bowl" src="https://sketchfab.com/models/abc/embed"></iframe>';
    $other = '<iframe title="Map" src="https://example.com/map"></iframe>';
    return [
      'A Sketchfab embed counts as a 3D model' => [['image', 'external_embed'], [$sketchfab], ['image', '3d_model']],
      'Another embed stays an external embed' => [['external_embed'], [$other], ['external_embed']],
      'Both kinds of embed count as both' => [['external_embed'], [$other, $sketchfab], ['external_embed', '3d_model']],
      'A Sketchfab embed and an uploaded model count once' => [
        ['3d_model', 'external_embed'],
        [$sketchfab],
        ['3d_model'],
      ],
    ];
  }

  /**
   * Tests that embeds from 3D model hosts are indexed as 3D models (#336).
   */
  #[DataProvider('embedProvider')]
  public function testEmbedsFromModelHostsCountAsModels(array $bundles, array $embed_codes, array $expected): void {
    $item = $this->createItem([
      'media_type' => ['entity:node', 'field_media_assets:entity:bundle', $bundles],
    ]);
    $item->setOriginalObject($this->nodeWithEmbeds($embed_codes));

    $this->dispatch($item);

    $this->assertSame($expected, $item->getField('media_type')->getValues());
  }

  /**
   * Builds a stand-in for a node whose media assets are the given embeds.
   */
  protected function nodeWithEmbeds(array $embed_codes): ComplexDataInterface {
    $media = [];
    foreach ($embed_codes as $code) {
      $embed = $this->createMock(FieldItemListInterface::class);
      $embed->method('__get')->with('value')->willReturn($code);
      $item = $this->createMock(FieldableEntityInterface::class);
      $item->method('bundle')->willReturn('external_embed');
      $item->method('hasField')->willReturn(TRUE);
      $item->method('get')->willReturn($embed);
      $media[] = $item;
    }
    $assets = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $assets->method('referencedEntities')->willReturn($media);
    $node = $this->createMock(FieldableEntityInterface::class);
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->with('field_media_assets')->willReturn($assets);
    $data = $this->createMock(ComplexDataInterface::class);
    $data->method('getValue')->willReturn($node);
    return $data;
  }

  /**
   * Tests that the subscriber is registered for the indexing event.
   */
  public function testSubscriberIsRegistered(): void {
    $item = $this->createItem([
      'media_type' => ['entity:node', 'field_media_assets:entity:bundle', ['soundcloud']],
    ]);

    $event = new IndexingItemsEvent($this->index, ['entity:node/1:en' => $item]);
    $this->container->get('event_dispatcher')->dispatch($event, SearchApiEvents::INDEXING_ITEMS);

    $this->assertSame(['audio'], $item->getField('media_type')->getValues());
  }

  /**
   * Tests that other bundle fields are left alone.
   */
  public function testOtherFieldsUnchanged(): void {
    $item = $this->createItem([
      'page_media_type' => ['entity:node', 'field_page_media_assets:entity:bundle', ['soundcloud']],
      'media_bundle' => ['entity:media', 'bundle', ['remote_video']],
    ]);

    $this->dispatch($item);

    $this->assertSame(['soundcloud'], $item->getField('page_media_type')->getValues());
    $this->assertSame(['remote_video'], $item->getField('media_bundle')->getValues());
  }

  /**
   * Creates an indexed node item with the given fields already extracted.
   *
   * @param array $fields
   *   Arrays of [datasource ID, property path, values], keyed by field ID.
   */
  protected function createItem(array $fields): ItemInterface {
    $fields_helper = $this->container->get('search_api.fields_helper');
    $item = $fields_helper->createItem($this->index, 'entity:node/1:en');
    foreach ($fields as $field_id => [$datasource_id, $property_path, $values]) {
      $field = $fields_helper->createField($this->index, $field_id, [
        'datasource_id' => $datasource_id,
        'property_path' => $property_path,
        'type' => 'string',
      ]);
      $field->setValues($values);
      $item->setField($field_id, $field);
    }
    $item->setFieldsExtracted(TRUE);
    return $item;
  }

  /**
   * Runs the subscriber on a single item.
   */
  protected function dispatch(ItemInterface $item): void {
    $event = new IndexingItemsEvent($this->index, ['entity:node/1:en' => $item]);
    (new MediaTypeIndexingItemsSubscriber())->onIndexingItems($event);
  }

}
