<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\comment\Entity\Comment;
use Drupal\comment\CommentInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Tests the view-access gate on MukurtuCommentAccessControlHandler.
 *
 * 'administer comments' (site-wide or protocol-scoped) must never grant
 * access to a comment on content the account cannot otherwise view.
 */
#[Group('mukurtu_protocol')]
class MukurtuCommentAccessControlHandlerTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'comment',
    'field',
    'filter',
    'geofield',
    'leaflet',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'user',
    'taxonomy',
    'mukurtu_core',
    'mukurtu_protocol',
  ];

  /**
   * A dummy community. Protocols require a community reference.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

  /**
   * A user not involved in testing to use as the owner for content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'filter', 'comment']);
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', 'sequences');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installSchema('comment', ['comment_entity_statistics']);

    node_access_rebuild();

    // Flag protocol/community entities as Og groups so Og does its part for
    // access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a node type to test under protocol control, with a comment
    // field attached.
    NodeType::create([
      'type' => 'thing',
      'name' => 'Protocol Controlled Thing',
    ])->save();
    $this->addDefaultCommentField('node', 'thing');

    // Standard authenticated user permissions: content access and comment
    // viewing, but *not* 'administer comments' - that's granted per-test to
    // the specific accounts under test.
    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->grantPermission('access comments');
    $role->save();

    // OG protocol steward role, scoped per-protocol via OG membership - the
    // Mukurtu-specific path this access handler exists to support.
    $stewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => ['administer comments'],
    ]);
    $stewardRole->setGroupType('protocol');
    $stewardRole->setGroupBundle('protocol');
    $stewardRole->save();

    // User to own content in tests where the tested user shouldn't be the
    // owner. As the first user entity created, this becomes uid 1, which
    // CulturalProtocolItem::preSave() exempts from its "can the saving user
    // apply this protocol" check - set as the active user so that setting
    // protocols on test content isn't silently stripped on save.
    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();
    $this->owner = $owner;
    \Drupal::getContainer()->get('current_user')->setAccount($owner);

    $this->community = Community::create(['name' => 'Community 1']);
    $this->community->save();
    $this->community->addMember($owner);
  }

  /**
   * Creates a strict protocol (no content).
   */
  protected function createProtocol(): Protocol {
    $protocol = Protocol::create([
      'name' => $this->randomString(),
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();
    return $protocol;
  }

  /**
   * Creates a published node gated by the given protocol.
   *
   * Node view access for published content is resolved through Drupal's
   * static node-access grants table, populated from protocol membership at
   * *save* time - so any membership that should affect view access must be
   * added to the protocol before calling this, not after.
   */
  protected function createContentForProtocol(Protocol $protocol): Node {
    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'thing',
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
    assert($node instanceof CulturalProtocolControlledInterface);
    $node->setSharingSetting('any');
    $node->setProtocols([$protocol]);
    $node->save();
    return $node;
  }

  /**
   * Creates a strict protocol and a node gated by it, with no memberships.
   *
   * @return array
   *   [Protocol $protocol, Node $node].
   */
  protected function createProtocolAndContent(): array {
    $protocol = $this->createProtocol();
    return [$protocol, $this->createContentForProtocol($protocol)];
  }

  /**
   * Creates a comment attached to the given node.
   */
  protected function createComment(Node $node, bool $published): CommentInterface {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comment',
      'comment_type' => 'comment',
      'subject' => $this->randomString(),
      'uid' => $this->owner->id(),
      'status' => $published ? CommentInterface::PUBLISHED : CommentInterface::NOT_PUBLISHED,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * A user with the raw, global 'administer comments' Drupal permission -
   * mirrors a real site admin or the Mukurtu Manager role.
   */
  protected function createGlobalCommentAdmin(): User {
    $role = Role::create([
      'id' => $this->randomMachineName(8),
      'label' => 'Global Comment Admin',
    ]);
    $role->grantPermission('administer comments');
    $role->save();

    $user = User::create(['name' => $this->randomString()]);
    $user->addRole($role->id());
    $user->save();
    return $user;
  }

  /**
   * The actual regression case: a global 'administer comments' holder must
   * not be able to view/update/delete/approve a comment on content they
   * cannot otherwise view. Before the fix, this was allowed unconditionally.
   */
  public function testGlobalCommentAdminDeniedWithoutContentViewAccess() {
    [, $node] = $this->createProtocolAndContent();
    $admin = $this->createGlobalCommentAdmin();

    // Sanity check: the account genuinely cannot view the gating content.
    $this->assertFalse($node->access('view', $admin));

    $published = $this->createComment($node, TRUE);
    $unpublished = $this->createComment($node, FALSE);

    $this->assertFalse($published->access('view', $admin), 'View denied on a comment attached to inaccessible content.');
    $this->assertFalse($published->access('update', $admin), 'Update denied on a comment attached to inaccessible content.');
    $this->assertFalse($published->access('delete', $admin), 'Delete denied on a comment attached to inaccessible content.');
    $this->assertFalse($unpublished->access('view', $admin), 'View of an unpublished comment denied on inaccessible content.');
    $this->assertFalse($unpublished->access('approve', $admin), 'Approve denied on a comment attached to inaccessible content.');
  }

  /**
   * No regression: a global 'administer comments' holder who *can* view the
   * gating content keeps full comment management access.
   */
  public function testGlobalCommentAdminAllowedWithContentViewAccess() {
    $protocol = $this->createProtocol();
    $admin = $this->createGlobalCommentAdmin();

    // Give the account membership (the base 'member' OG role) in the
    // protocol, sufficient for view access on a strict protocol. Node view
    // access for published content is resolved from grants computed at node
    // save time, so membership must be added before the node is created.
    $protocol->addMember($admin, ['member']);
    $node = $this->createContentForProtocol($protocol);

    $this->assertTrue($node->access('view', $admin));

    $published = $this->createComment($node, TRUE);
    $unpublished = $this->createComment($node, FALSE);

    $this->assertTrue($published->access('view', $admin));
    $this->assertTrue($published->access('update', $admin));
    $this->assertTrue($published->access('delete', $admin));
    $this->assertTrue($unpublished->access('view', $admin));
    $this->assertTrue($unpublished->access('approve', $admin));
  }

  /**
   * No regression: an OG protocol steward (permission scoped to the same
   * protocol gating the content) keeps full comment management access on
   * their own protocol's content.
   */
  public function testProtocolStewardAllowedOnOwnProtocol() {
    $protocol = $this->createProtocol();

    $steward = User::create(['name' => $this->randomString()]);
    $steward->save();
    // Membership must be added before the node is created; see
    // createContentForProtocol().
    $protocol->addMember($steward, ['protocol_steward']);
    $node = $this->createContentForProtocol($protocol);

    $this->assertTrue($node->access('view', $steward));

    $published = $this->createComment($node, TRUE);
    $unpublished = $this->createComment($node, FALSE);

    $this->assertTrue($published->access('view', $steward));
    $this->assertTrue($published->access('update', $steward));
    $this->assertTrue($published->access('delete', $steward));
    $this->assertTrue($unpublished->access('view', $steward));
    $this->assertTrue($unpublished->access('approve', $steward));
  }

  /**
   * A protocol steward of a *different* protocol than the one gating the
   * content has no OG permission on the content's protocol, so is denied -
   * this was already correctly denied before the fix (the OG membership
   * lookup itself fails), but is worth guarding against regression.
   */
  public function testProtocolStewardOfDifferentProtocolDenied() {
    [, $node] = $this->createProtocolAndContent();
    [$otherProtocol,] = $this->createProtocolAndContent();

    $steward = User::create(['name' => $this->randomString()]);
    $steward->save();
    $otherProtocol->addMember($steward, ['protocol_steward']);

    $this->assertFalse($node->access('view', $steward));

    $comment = $this->createComment($node, FALSE);
    $this->assertFalse($comment->access('view', $steward));
    $this->assertFalse($comment->access('update', $steward));
    $this->assertFalse($comment->access('delete', $steward));
    $this->assertFalse($comment->access('approve', $steward));
  }

  /**
   * Cacheable metadata (user.permissions context, and cache tags for both
   * the comment and the commented entity) must survive on both an allowed
   * and a denied result, guarding against a regression to an
   * AccessResult::allowedIf()-style loss of cacheability.
   */
  public function testCacheableMetadataPreserved() {
    [, $inaccessibleNode] = $this->createProtocolAndContent();
    $deniedComment = $this->createComment($inaccessibleNode, FALSE);

    $deniedAdmin = $this->createGlobalCommentAdmin();
    $deniedResult = $deniedComment->access('approve', $deniedAdmin, TRUE);
    $this->assertFalse($deniedResult->isAllowed());
    $this->assertContains('user.permissions', $deniedResult->getCacheContexts());

    $accessibleProtocol = $this->createProtocol();
    $allowedAdmin = $this->createGlobalCommentAdmin();
    $accessibleProtocol->addMember($allowedAdmin, ['member']);
    $accessibleNode = $this->createContentForProtocol($accessibleProtocol);
    $allowedComment = $this->createComment($accessibleNode, FALSE);

    $allowedResult = $allowedComment->access('approve', $allowedAdmin, TRUE);
    $this->assertTrue($allowedResult->isAllowed());
    $this->assertContains('user.permissions', $allowedResult->getCacheContexts());
  }

}
