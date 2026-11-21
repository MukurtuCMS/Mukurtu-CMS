<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_pwa\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Save to this device" button on item pages.
 */
#[Group('mukurtu_pwa')]
class SaveButtonTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_api',
    'mukurtu_core',
    'mukurtu_design',
    'mukurtu_protocol',
    'mukurtu_pwa',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['og', 'system', 'filter', 'node']);
    foreach (['community', 'node', 'og_membership', 'protocol', 'taxonomy_term', 'user', 'workflow'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', ['mukurtu_protocol_map', 'mukurtu_protocol_access']);

    // Run fixture setup as uid 1, so protocols are kept on save.
    $admin = User::create(['name' => 'admin', 'status' => 1]);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);
    Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous'])
      ->grantPermission('access content')
      ->save();

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');
    \Drupal::service(NodeAccessRebuild::class)->rebuild();
    NodeType::create(['type' => 'thing', 'name' => 'Thing'])->save();
  }

  /**
   * Creates a published item under a new protocol.
   */
  protected function item(string $mode): NodeInterface {
    $community = Community::create(['name' => 'Community', 'field_access_mode' => 'public']);
    $community->save();
    $protocol = Protocol::create([
      'name' => "Protocol $mode",
      'field_communities' => [$community->id()],
      'field_access_mode' => $mode,
    ]);
    $protocol->save();
    $node = Node::create(['type' => 'thing', 'title' => "Item $mode", 'status' => 1]);
    $node->setSharingSetting('any');
    $node->setProtocols([$protocol]);
    $node->save();
    return $node;
  }

  /**
   * Builds an item's render array in a view mode.
   */
  protected function build(NodeInterface $node, string $viewMode): array {
    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    return $builder->build($builder->view($node, $viewMode));
  }

  /**
   * Open items are marked open; restricted items are marked restricted.
   */
  public function testAccessMarker(): void {
    $open = $this->build($this->item('open'), 'full')['mukurtu_pwa_save'];
    $this->assertSame('open', $open['#attributes']['data-offline-access']);
    $this->assertSame('Item open', $open['#attributes']['data-offline-title']);
    $this->assertSame('hidden', $open['#attributes']['hidden']);
    $this->assertContains('mukurtu_pwa/save', $open['#attached']['library']);

    $strict = $this->build($this->item('strict'), 'full')['mukurtu_pwa_save'];
    $this->assertSame('restricted', $strict['#attributes']['data-offline-access']);
  }

  /**
   * The marker is cleared when the item's access changes.
   */
  public function testCacheability(): void {
    $node = $this->item('open');
    $build = $this->build($node, 'full');
    $this->assertContains("node:{$node->id()}", $build['#cache']['tags']);
  }

  /**
   * Only the full view gets the button.
   */
  public function testOnlyFullView(): void {
    $this->assertArrayNotHasKey('mukurtu_pwa_save', $this->build($this->item('open'), 'teaser'));
  }

}
