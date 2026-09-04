<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_landing_page_update_40006().
 *
 * Part A widens the landing page inline block allowlist; Part B converts the
 * default homepage's Featured Content component from a reusable "Content block"
 * plugin to a Layout Builder inline block so its Configure dialog exposes the
 * "Select Content" entity browser.
 *
 * This hook covers upgrades only; fresh-install parity lives in
 * DefaultLandingPageTest.
 *
 * @see mukurtu_landing_page_update_40006()
 */
#[Group('mukurtu_landing_page')]
class FeaturedContentInlineBlockUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
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

  /**
   * The block_content UUID tracked in state as the default Featured Content.
   *
   * @var string
   */
  protected string $featuredUuid;

  /**
   * The landing page node acting as the site homepage.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $homepage;

  /**
   * {@inheritdoc}
   */
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

    // The featured_content block bundle + its fields and the landing_page node
    // bundle live in the install profile's / mukurtu_core's config/install,
    // which installConfig() does not pull in for this module. Import them
    // through the entity API so field storage tables are actually created.
    $profile_storage = new FileStorage($this->root . '/profiles/mukurtu/config/install');
    $this->importConfigEntity($profile_storage, 'block_content.type.featured_content', 'block_content_type');
    $this->importConfigEntity($profile_storage, 'field.storage.block_content.body', 'field_storage_config');
    $this->importConfigEntity($profile_storage, 'field.storage.block_content.field_featured_content', 'field_storage_config');
    $this->importConfigEntity($profile_storage, 'field.field.block_content.featured_content.body', 'field_config');
    $this->importConfigEntity($profile_storage, 'field.field.block_content.featured_content.field_featured_content', 'field_config');

    $mukurtu_core_storage = new FileStorage($this->root . '/profiles/mukurtu/modules/mukurtu_core/config/install');
    $this->importConfigEntity($mukurtu_core_storage, 'field.storage.node.layout_builder__layout', 'field_storage_config');

    $landing_page_storage = new FileStorage($this->root . '/profiles/mukurtu/modules/mukurtu_landing_page/config/install');
    $this->importConfigEntity($landing_page_storage, 'node.type.landing_page', 'node_type');
    $this->importConfigEntity($landing_page_storage, 'field.field.node.landing_page.layout_builder__layout', 'field_config');

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_landing_page');
    require_once $module_path . '/mukurtu_landing_page.install';

    $this->seedFixture();
  }

  /**
   * Reads a config file and creates/saves it as the given config entity type.
   */
  protected function importConfigEntity(FileStorage $storage, string $config_name, string $entity_type_id): void {
    $data = $storage->read($config_name);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage_handler */
    $storage_handler = $this->container->get('entity_type.manager')->getStorage($entity_type_id);
    $storage_handler->createFromStorageRecord($data)->save();
  }

  /**
   * Builds the pre-update state: reusable block + homepage + narrow allowlist.
   */
  protected function seedFixture(): void {
    $block = BlockContent::create([
      'type' => 'featured_content',
      'info' => 'Featured Content',
      'reusable' => TRUE,
      'field_featured_content' => [],
    ]);
    $block->save();
    $this->featuredUuid = $block->uuid();

    \Drupal::state()->set('mukurtu_landing_page.default_blocks', ['featured' => $this->featuredUuid]);

    $component = new SectionComponent('featured-component-uuid', 'content', [
      'id' => 'block_content:' . $this->featuredUuid,
      'label' => 'Featured Content',
      'label_display' => 1,
      'provider' => 'block_content',
    ]);
    $section = new Section('layout_onecol', [], [$component]);

    $this->homepage = Node::create([
      'type' => 'landing_page',
      'title' => 'Mukurtu Homepage',
      'status' => TRUE,
      'uid' => 1,
    ]);
    $this->homepage->set('layout_builder__layout', [$section]);
    $this->homepage->save();

    \Drupal::configFactory()->getEditable('system.site')
      ->set('page.front', '/node/' . $this->homepage->id())
      ->save();

    \Drupal::configFactory()->getEditable('core.entity_view_display.node.landing_page.default')
      ->setData([
        'langcode' => 'en',
        'status' => TRUE,
        'dependencies' => [],
        'third_party_settings' => [
          'layout_builder_restrictions' => [
            'entity_view_mode_restriction' => [
              'allowlisted_blocks' => [
                'Inline blocks' => [
                  'inline_block:basic',
                  'inline_block:local_contexts_block',
                ],
              ],
              'restricted_categories' => ['Inline blocks', 'Custom'],
            ],
          ],
        ],
        'id' => 'node.landing_page.default',
        'targetEntityType' => 'node',
        'bundle' => 'landing_page',
        'mode' => 'default',
        'content' => [],
        'hidden' => [],
      ])
      ->save();
  }

  /**
   * Reloads the homepage node from storage and returns its first component.
   */
  protected function reloadFeaturedComponent(): SectionComponent {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $node = $storage->load($this->homepage->id());
    $sections = $node->get('layout_builder__layout')->getSections();
    $components = $sections[0]->getComponents();
    return reset($components);
  }

  /**
   * Returns the restriction config subtree for the landing page default display.
   */
  protected function getRestrictionConfig(): array {
    return \Drupal::config('core.entity_view_display.node.landing_page.default')
      ->get('third_party_settings.layout_builder_restrictions.entity_view_mode_restriction');
  }

  /**
   * The component is rewritten to an inline block referencing the same content.
   */
  public function testConvertsComponentToInlineBlock(): void {
    mukurtu_landing_page_update_40006();

    $configuration = $this->reloadFeaturedComponent()->get('configuration');
    $this->assertSame('inline_block:featured_content', $configuration['id']);
    $this->assertSame('layout_builder', $configuration['provider']);
    $this->assertSame('full', $configuration['view_mode']);
    $this->assertSame('Featured Content', $configuration['label']);
    $this->assertSame(1, $configuration['label_display']);
    $this->assertNotEmpty($configuration['block_revision_id']);
    $this->assertNull($configuration['block_serialized']);

    /** @var \Drupal\block_content\BlockContentInterface $revision */
    $revision = $this->container->get('entity_type.manager')->getStorage('block_content')
      ->loadRevision($configuration['block_revision_id']);
    $this->assertSame($this->featuredUuid, $revision->uuid());
  }

  /**
   * The block becomes non-reusable on a new revision, keeping its field data.
   */
  public function testBlockBecomesNonReusableWithNewRevision(): void {
    $block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $original = $block_storage->loadByProperties(['uuid' => $this->featuredUuid]);
    $original = reset($original);
    $original_revision_id = $original->getRevisionId();
    $original->set('field_featured_content', []);
    $original->save();

    mukurtu_landing_page_update_40006();

    $block_storage->resetCache();
    $reloaded = $block_storage->loadByProperties(['uuid' => $this->featuredUuid]);
    $reloaded = reset($reloaded);
    $this->assertFalse($reloaded->isReusable());
    $this->assertGreaterThan($original_revision_id, $reloaded->getRevisionId());
    $this->assertTrue($reloaded->hasField('field_featured_content'));
  }

  /**
   * The inline block's usage is recorded against the homepage node.
   */
  public function testUsageRecorded(): void {
    mukurtu_landing_page_update_40006();

    $block = $this->container->get('entity_type.manager')->getStorage('block_content')
      ->loadByProperties(['uuid' => $this->featuredUuid]);
    $block = reset($block);

    $usage = \Drupal::service('inline_block.usage')->getUsage($block->id());
    $this->assertNotNull($usage);
    $this->assertEquals($this->homepage->id(), $usage->layout_entity_id);
    $this->assertSame('node', $usage->layout_entity_type);
  }

  /**
   * Part A replaces the inline allowlist and clears the restricted category.
   */
  public function testAllowlistUpdated(): void {
    mukurtu_landing_page_update_40006();

    $restriction = $this->getRestrictionConfig();
    $this->assertSame([
      'inline_block:basic',
      'inline_block:call_to_action',
      'inline_block:featured_content',
      'inline_block:horizontal_divider',
      'inline_block:local_contexts_block',
      'inline_block:media_carousel_block',
    ], $restriction['allowlisted_blocks']['Inline blocks']);
    $this->assertNotContains('Inline blocks', $restriction['restricted_categories']);
    $this->assertContains('Custom', $restriction['restricted_categories']);
  }

  /**
   * Running the hook twice leaves the component and block revision untouched.
   */
  public function testIdempotent(): void {
    mukurtu_landing_page_update_40006();
    $first = $this->reloadFeaturedComponent()->get('configuration');

    mukurtu_landing_page_update_40006();
    $second = $this->reloadFeaturedComponent()->get('configuration');

    $this->assertSame($first, $second);

    $block = $this->container->get('entity_type.manager')->getStorage('block_content')
      ->loadByProperties(['uuid' => $this->featuredUuid]);
    $block = reset($block);
    $usage_rows = (int) \Drupal::database()->select('inline_block_usage')
      ->condition('block_content_id', $block->id())
      ->countQuery()->execute()->fetchField();
    $this->assertSame(1, $usage_rows);
  }

  /**
   * With no tracked block, Part B is skipped but Part A still runs.
   */
  public function testNoOpWhenStateMissing(): void {
    \Drupal::state()->delete('mukurtu_landing_page.default_blocks');

    mukurtu_landing_page_update_40006();

    $this->assertSame(
      'block_content:' . $this->featuredUuid,
      $this->reloadFeaturedComponent()->get('configuration')['id']
    );
    $this->assertContains(
      'inline_block:featured_content',
      $this->getRestrictionConfig()['allowlisted_blocks']['Inline blocks']
    );
  }

  /**
   * When the front page is not a node route, the component is left alone.
   */
  public function testNoOpWhenFrontPageNotNode(): void {
    \Drupal::configFactory()->getEditable('system.site')->set('page.front', '/')->save();

    mukurtu_landing_page_update_40006();

    $this->assertSame(
      'block_content:' . $this->featuredUuid,
      $this->reloadFeaturedComponent()->get('configuration')['id']
    );
  }

}
