<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DefaultLandingPage::createDefaultLandingPage() block reuse.
 */
#[Group('mukurtu_landing_page')]
class DefaultLandingPageTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'field',
    'text',
    'filter',
    'options',
    'link',
    'image',
    'file',
    'media',
    'user',
    'node',
    'block',
    'block_content',
    'layout_builder',
    'layout_discovery',
    'path',
    'path_alias',
    'menu_ui',
    'geofield',
    'leaflet',
    'mukurtu_core',
    'mukurtu_landing_page',
  ];

  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('block_content');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('layout_builder', ['inline_block_usage']);

    $this->installConfig(['field', 'filter', 'node']);

    // The block_content bundles used by the default landing page (and their
    // field storage/instance config) are not owned by mukurtu_landing_page -
    // they live in the Mukurtu install profile's own config/install
    // directory. installConfig() only pulls a module's own config, so import
    // the profile-level config directly via the entity API (not a raw
    // config-storage write) so that field storage tables actually get
    // created - a plain Config::save() bypasses FieldStorageConfig's
    // postSave() hook that triggers table creation. Order matters: bundles
    // (no dependencies) first, then field storages, then field instances.
    $profile_storage = new FileStorage($this->root . '/profiles/mukurtu/config/install');

    $bundles = [
      'block_content.type.image_with_description',
      'block_content.type.vertical_image_with_description',
      'block_content.type.featured_content',
      'block_content.type.full_image_with_description',
    ];
    $field_storages = [
      'field.storage.block_content.body',
      'field.storage.block_content.field_image',
      'field.storage.block_content.field_featured_content',
      'field.storage.block_content.field_text_color',
    ];
    $field_instances = [
      'field.field.block_content.image_with_description.body',
      'field.field.block_content.image_with_description.field_image',
      'field.field.block_content.vertical_image_with_description.body',
      'field.field.block_content.vertical_image_with_description.field_image',
      'field.field.block_content.featured_content.body',
      'field.field.block_content.featured_content.field_featured_content',
      'field.field.block_content.full_image_with_description.body',
      'field.field.block_content.full_image_with_description.field_image',
      'field.field.block_content.full_image_with_description.field_text_color',
    ];

    foreach ($bundles as $name) {
      $this->importConfigEntity($profile_storage, $name, 'block_content_type');
    }
    foreach ($field_storages as $name) {
      $this->importConfigEntity($profile_storage, $name, 'field_storage_config');
    }
    foreach ($field_instances as $name) {
      $this->importConfigEntity($profile_storage, $name, 'field_config');
    }

    // The layout_builder__layout field storage used by the landing_page
    // node bundle is provided by mukurtu_core's config/install (also not
    // pulled in automatically since mukurtu_landing_page owns the bundle
    // and per-bundle field instance, not mukurtu_core).
    $mukurtu_core_storage = new FileStorage($this->root . '/profiles/mukurtu/modules/mukurtu_core/config/install');
    $this->importConfigEntity($mukurtu_core_storage, 'field.storage.node.layout_builder__layout', 'field_storage_config');

    // Import the landing_page node bundle and its layout_builder__layout
    // field instance from mukurtu_landing_page's own config/install.
    $landing_page_storage = new FileStorage($this->root . '/profiles/mukurtu/modules/mukurtu_landing_page/config/install');
    $this->importConfigEntity($landing_page_storage, 'node.type.landing_page', 'node_type');
    $this->importConfigEntity($landing_page_storage, 'field.field.node.landing_page.layout_builder__layout', 'field_config');
  }

  /**
   * Reads a config file and creates/saves it as the given config entity type.
   *
   * Using the entity API (rather than a raw config-storage write) ensures
   * side effects like field storage table creation actually happen.
   *
   * @param \Drupal\Core\Config\FileStorage $storage
   *   The file storage to read from.
   * @param string $config_name
   *   The config object name (without the storage's collection prefix).
   * @param string $entity_type_id
   *   The config entity type to create.
   */
  protected function importConfigEntity(FileStorage $storage, string $config_name, string $entity_type_id): void {
    $data = $storage->read($config_name);
    // createFromStorageRecord() (rather than create()) runs the storage's
    // mapFromStorageRecords() first, which lets field types like
    // list_string transform their on-disk settings format (e.g.
    // allowed_values as a list of {value, label} pairs) into the runtime
    // format before the entity is built. Using create() directly with raw
    // on-disk data leaves settings in on-disk shape, and then preSave()
    // re-applies the disk-format transform on top of it, corrupting
    // settings like allowed_values.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage_handler */
    $storage_handler = $this->container->get('entity_type.manager')->getStorage($entity_type_id);
    $storage_handler->createFromStorageRecord($data)->save();
  }

  /**
   * Extracts block_content UUIDs referenced by a landing page's layout.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The landing page node.
   *
   * @return array
   *   The block_content UUIDs referenced by the node's Layout Builder
   *   section, keyed by component UUID.
   */
  protected function getBlockUuidsFromLayout($node): array {
    $uuids = [];
    /** @var \Drupal\layout_builder\Section[] $sections */
    $sections = $node->get('layout_builder__layout')->getSections();
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $component) {
        $id = $component->get('configuration')['id'] ?? '';
        if (str_starts_with($id, 'block_content:')) {
          $uuids[$component->getUuid()] = substr($id, strlen('block_content:'));
        }
      }
    }
    return $uuids;
  }

  /**
   * Tests that a second call reuses the same block_content entities.
   */
  public function testSecondCallReusesBlocks(): void {
    /** @var \Drupal\mukurtu_landing_page\DefaultLandingPage $service */
    $service = \Drupal::service('mukurtu_landing_page.default_landing_page');

    $first_node = $service->createDefaultLandingPage();
    $this->assertNotNull($first_node);

    $first_block_uuids = $this->getBlockUuidsFromLayout($first_node);
    $this->assertNotEmpty($first_block_uuids);

    $first_block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $first_count = count($first_block_storage->loadMultiple());
    $this->assertSame(4, $first_count, 'The first call should create exactly 4 block_content entities.');

    $second_node = $service->createDefaultLandingPage();
    $this->assertNotNull($second_node);

    // A new landing page node is created each time - that is expected.
    $this->assertNotSame($first_node->id(), $second_node->id());

    $second_block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    // Reset the static cache so we get a fresh count from storage.
    $second_block_storage->resetCache();
    $second_count = count($second_block_storage->loadMultiple());
    $this->assertSame(4, $second_count, 'The second call must not create duplicate block_content entities.');

    $second_block_uuids = $this->getBlockUuidsFromLayout($second_node);
    $this->assertNotEmpty($second_block_uuids);

    // The set of block_content UUIDs referenced must be identical - the
    // same blocks are reused, not new ones created.
    sort($first_block_uuids);
    sort($second_block_uuids);
    $this->assertSame($first_block_uuids, $second_block_uuids);
  }

  /**
   * Tests that a deleted tracked block is recreated, not left missing.
   */
  public function testCreatesNewBlockWhenTrackedOneDeleted(): void {
    /** @var \Drupal\mukurtu_landing_page\DefaultLandingPage $service */
    $service = \Drupal::service('mukurtu_landing_page.default_landing_page');

    $first_node = $service->createDefaultLandingPage();
    $first_block_uuids = $this->getBlockUuidsFromLayout($first_node);

    /** @var \Drupal\block_content\BlockContentStorageInterface $block_storage */
    $block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');

    // Find and delete the featured_content block.
    $featured_blocks = $block_storage->loadByProperties(['type' => 'featured_content']);
    $this->assertCount(1, $featured_blocks);
    $deleted_block = reset($featured_blocks);
    $deleted_uuid = $deleted_block->uuid();
    $block_storage->delete([$deleted_block]);

    $block_storage->resetCache();
    $this->assertCount(3, $block_storage->loadMultiple());

    $second_node = $service->createDefaultLandingPage();
    $block_storage->resetCache();

    // The deleted block was recreated, not duplicated - back to 4 total.
    $this->assertCount(4, $block_storage->loadMultiple());

    $second_block_uuids = $this->getBlockUuidsFromLayout($second_node);

    // The 3 untouched blocks kept their original UUIDs (reused).
    $untouched_first = array_diff($first_block_uuids, [$deleted_uuid]);
    foreach ($untouched_first as $uuid) {
      $this->assertContains($uuid, $second_block_uuids, 'Untouched blocks should keep their original UUID.');
    }

    // The deleted block's bundle now has a fresh entity with a new UUID.
    $new_featured_blocks = $block_storage->loadByProperties(['type' => 'featured_content']);
    $this->assertCount(1, $new_featured_blocks);
    $new_featured_block = reset($new_featured_blocks);
    $this->assertNotSame($deleted_uuid, $new_featured_block->uuid(), 'The recreated block must have a new UUID, not the deleted one.');
    $this->assertContains($new_featured_block->uuid(), $second_block_uuids, 'The layout should reference the newly recreated block.');
  }

}
