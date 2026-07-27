<?php

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\node\Entity\Node;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Hook\CommunityProtocolList;
use Drupal\user\Entity\Role;

/**
 * Tests the CommunityProtocolListBlock plugin.
 *
 * Verifies the block reuses
 * \Drupal\mukurtu_protocol\Hook\CommunityProtocolList::buildCommunityProtocolList()
 * instead of duplicating its logic.
 */
#[Group('mukurtu_protocol')]
class CommunityProtocolListBlockTest extends ProtocolAwareEntityTestBase {

  /**
   * A protocol/community pair visible to any user.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   */
  protected $openProtocol;

  /**
   * A protocol/community pair only visible to members.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   */
  protected $strictProtocol;

  /**
   * The node under test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $entity;

  /**
   * A plain, non-privileged user.
   *
   * The base class's current user is UID 1, which bypasses all access
   * checks via core's superuser access policy.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $viewer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required by the block plugin manager's block_content deriver, which
    // queries this table when discovering block plugin definitions.
    $this->installEntitySchema('block_content');

    // Grant the authenticated role permission to view public communities,
    // so the "open" case below can actually resolve to a link.
    Role::load('authenticated')
      ->grantPermission('view published community entities')
      ->save();

    $viewer = $this->createUser();
    $viewer->save();
    $this->viewer = $viewer;

    $openCommunity = Community::create([
      'name' => 'Open Community',
      'status' => TRUE,
      'field_access_mode' => 'public',
    ]);
    $openCommunity->save();
    $this->openProtocol = Protocol::create([
      'name' => 'Open Protocol',
      'field_communities' => [$openCommunity->id()],
      'field_access_mode' => 'open',
    ]);
    $this->openProtocol->save();

    $strictCommunity = Community::create([
      'name' => 'Strict Community',
      'status' => TRUE,
      'field_access_mode' => 'community-only',
    ]);
    $strictCommunity->save();
    $this->strictProtocol = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$strictCommunity->id()],
      'field_access_mode' => 'strict',
    ]);
    $this->strictProtocol->save();

    $entity = Node::create([
      'title' => $this->randomString(),
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $entity->setSharingSetting('any');
    $entity->setProtocols([$this->openProtocol->id(), $this->strictProtocol->id()]);
    $entity->save();
    $this->entity = $entity;

    $this->setCurrentUser($this->viewer);
  }

  /**
   * The block should produce the same render array as the shared builder.
   */
  public function testBlockDelegatesToSharedBuilder(): void {
    $expected = $this->container
      ->get('class_resolver')
      ->getInstanceFromDefinition(CommunityProtocolList::class)
      ->buildCommunityProtocolList($this->entity);

    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_community_protocol_list', []);
    $block->setContextValue('node', $this->entity);
    $build = $block->build();

    $this->assertEquals($expected, $build);
  }

  /**
   * Tests that access-restricted items fall back to a plain label.
   *
   * Accessible (open) protocols/communities render as links; inaccessible
   * (strict, non-member) ones fall back to their plain label.
   */
  public function testBlockRespectsProtocolAndCommunityAccess(): void {
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_community_protocol_list', []);
    $block->setContextValue('node', $this->entity);
    $build = $block->build();

    $this->assertCount(2, $build['#items']);

    $rendered_items = array_map(
      fn(array $item) => (string) $item['#markup'],
      $build['#items']
    );

    $this->assertTrue(
      (bool) array_filter($rendered_items, fn($markup) => substr_count($markup, '<a href') === 2 && str_contains($markup, 'Open Community') && str_contains($markup, 'Open Protocol')),
      'Open community/protocol pair should both render as links.'
    );
    $this->assertTrue(
      (bool) array_filter($rendered_items, fn($markup) => $markup === 'Strict Community: Strict Protocol'),
      'Strict community/protocol pair should fall back to plain labels for a non-member.'
    );
  }

}
