<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests front-end LB block edit URLs on nodes with and without an override.
 *
 * On a fresh install the homepage has no override of its own - its layout
 * lives in the display defaults - so the edit buttons used to be missing
 * until someone saved the layout once.
 */
#[Group('mukurtu_gin_custom')]
class LbFrontendEditUrlsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'block',
    'block_content',
    'layout_discovery',
    'layout_builder',
    'mukurtu_gin_custom',
  ];

  /**
   * UUID of the plugin block component in the default section.
   */
  protected const PLUGIN_COMPONENT_UUID = '11111111-1111-4111-8111-111111111111';

  /**
   * UUID of the reusable block_content component in the default section.
   */
  protected const BLOCK_CONTENT_COMPONENT_UUID = '22222222-2222-4222-8222-222222222222';

  /**
   * UUID of the field block component in the default section.
   */
  protected const FIELD_COMPONENT_UUID = '33333333-3333-4333-8333-333333333333';

  /**
   * The reusable block placed in the default layout.
   */
  protected BlockContent $blockContent;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installConfig(['filter', 'node', 'system']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();
    $this->blockContent = BlockContent::create([
      'type' => 'basic',
      'info' => 'Hero',
      'reusable' => TRUE,
    ]);
    $this->blockContent->save();

    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->enableLayoutBuilder()->setOverridable()->save();
    $display->removeAllSections();
    $display->appendSection(new Section('layout_onecol', [], [
      self::PLUGIN_COMPONENT_UUID => new SectionComponent(self::PLUGIN_COMPONENT_UUID, 'content', [
        'id' => 'system_powered_by_block',
      ]),
      self::BLOCK_CONTENT_COMPONENT_UUID => new SectionComponent(self::BLOCK_CONTENT_COMPONENT_UUID, 'content', [
        'id' => 'block_content:' . $this->blockContent->uuid(),
      ]),
      self::FIELD_COMPONENT_UUID => new SectionComponent(self::FIELD_COMPONENT_UUID, 'content', [
        'id' => 'field_block:node:page:title',
      ]),
    ]));
    $display->save();

    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * A node on the display defaults gets URLs for the defaults' blocks.
   */
  public function testNonOverriddenNodeUsesDefaultSections(): void {
    $node = $this->createNode();
    $this->assertTrue($node->get('layout_builder__layout')->isEmpty());

    $urls = $this->container->get('mukurtu_gin_custom.lb_frontend_edit_urls')->build($node, '/return');

    $this->assertEqualsCanonicalizing(
      [self::PLUGIN_COMPONENT_UUID, self::BLOCK_CONTENT_COMPONENT_UUID],
      array_keys($urls),
      'Field blocks are skipped; every other default block has an edit URL.',
    );
    $this->assertStringContainsString('/layout_builder/update/block/overrides/node.' . $node->id() . '/0/content/' . self::PLUGIN_COMPONENT_UUID, $urls[self::PLUGIN_COMPONENT_UUID]);
    $this->assertStringContainsString('frontend_edit=1', $urls[self::PLUGIN_COMPONENT_UUID]);
    $this->assertStringContainsString('/block/' . $this->blockContent->id(), $urls[self::BLOCK_CONTENT_COMPONENT_UUID]);
  }

  /**
   * An overridden node gets URLs for its own sections, not the defaults'.
   */
  public function testOverriddenNodeUsesOwnSections(): void {
    $override_uuid = '44444444-4444-4444-8444-444444444444';
    $node = $this->createNode();
    $node->get('layout_builder__layout')->appendSection(new Section('layout_onecol', [], [
      $override_uuid => new SectionComponent($override_uuid, 'content', [
        'id' => 'system_powered_by_block',
      ]),
    ]));
    $node->save();

    $urls = $this->container->get('mukurtu_gin_custom.lb_frontend_edit_urls')->build($node, '/return');

    $this->assertSame([$override_uuid], array_keys($urls));
  }

  /**
   * Opening a front-end edit URL copies the defaults into the override.
   */
  public function testSeedSubscriberCopiesDefaultsForFrontendEdit(): void {
    $section_storage = $this->loadOverrideStorage($this->createNode());
    $this->dispatchUpdateBlockRequest($section_storage, TRUE);

    $tempstore = $this->container->get('layout_builder.tempstore_repository');
    $this->assertTrue($tempstore->has($section_storage));
    $component = $tempstore->get($section_storage)->getSection(0)->getComponent(self::PLUGIN_COMPONENT_UUID);
    $this->assertSame('system_powered_by_block', $component->getPluginId());
  }

  /**
   * Without the frontend_edit flag, the core LB flow is left alone.
   */
  public function testSeedSubscriberIgnoresNonFrontendRequests(): void {
    $section_storage = $this->loadOverrideStorage($this->createNode());
    $this->dispatchUpdateBlockRequest($section_storage, FALSE);

    $this->assertFalse($this->container->get('layout_builder.tempstore_repository')->has($section_storage));
    $this->assertCount(0, $section_storage->getSections());
  }

  /**
   * An already overridden layout is not re-seeded from the defaults.
   */
  public function testSeedSubscriberIgnoresOverriddenLayouts(): void {
    $node = $this->createNode();
    $node->get('layout_builder__layout')->appendSection(new Section('layout_onecol'));
    $node->save();
    $section_storage = $this->loadOverrideStorage($node);
    $this->dispatchUpdateBlockRequest($section_storage, TRUE);

    $this->assertFalse($this->container->get('layout_builder.tempstore_repository')->has($section_storage));
    $this->assertCount(1, $section_storage->getSections());
  }

  /**
   * Creates a page node.
   */
  protected function createNode(): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => 'Test page']);
    $node->save();
    return $node;
  }

  /**
   * Loads the override section storage for a node.
   */
  protected function loadOverrideStorage(NodeInterface $node): OverridesSectionStorageInterface {
    $section_storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', [
      'entity' => EntityContext::fromEntity($node),
      'view_mode' => new Context(new ContextDefinition('string'), 'full'),
    ]);
    $this->assertInstanceOf(OverridesSectionStorageInterface::class, $section_storage);
    return $section_storage;
  }

  /**
   * Runs the seed subscriber against an update-block request.
   */
  protected function dispatchUpdateBlockRequest(OverridesSectionStorageInterface $section_storage, bool $frontend_edit): void {
    $request = Request::create('/layout_builder/update/block', 'GET', $frontend_edit ? ['frontend_edit' => '1'] : []);
    $request->attributes->set('_route', 'layout_builder.update_block');
    $request->attributes->set('section_storage', $section_storage);
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get('mukurtu_gin_custom.lb_frontend_edit_seed_subscriber')->onRequest($event);
  }

}
