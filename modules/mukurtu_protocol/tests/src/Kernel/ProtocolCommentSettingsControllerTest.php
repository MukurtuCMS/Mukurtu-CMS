<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

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
use Drupal\mukurtu_protocol\Controller\ProtocolCommentSettingsController;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Tests the new "my protocol comments" page and unpublish action added to
 * ProtocolCommentSettingsController.
 */
#[Group('mukurtu_protocol')]
class ProtocolCommentSettingsControllerTest extends KernelTestBase {

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

    $this->installConfig(['og', 'filter', 'comment', 'system']);
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

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();
    $this->addDefaultCommentField('node', 'thing');

    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->grantPermission('access comments');
    $role->save();

    $stewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => ['administer comments'],
    ]);
    $stewardRole->setGroupType('protocol');
    $stewardRole->setGroupBundle('protocol');
    $stewardRole->save();

    // As the first user entity created, this becomes uid 1, which
    // CulturalProtocolItem::preSave() exempts from its "can the saving user
    // apply this protocol" check.
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
   * Instantiates the controller and runs its given method as the given user.
   */
  protected function callAsUser(User $account, string $method, ...$args) {
    \Drupal::getContainer()->get('current_user')->setAccount($account);
    $controller = ProtocolCommentSettingsController::create(\Drupal::getContainer());
    return $controller->{$method}(...$args);
  }

  /**
   * myComments() includes both published and unpublished comments on
   * content within a stewarded protocol, and excludes comments on
   * non-stewarded protocols' content.
   */
  public function testMyCommentsScopedToStewardedProtocols() {
    $stewardedProtocol = $this->createProtocol();
    $otherProtocol = $this->createProtocol();

    $steward = User::create(['name' => $this->randomString()]);
    $steward->save();
    $stewardedProtocol->addMember($steward, ['protocol_steward']);

    $stewardedNode = $this->createContentForProtocol($stewardedProtocol);
    $otherNode = $this->createContentForProtocol($otherProtocol);

    $publishedOwn = $this->createComment($stewardedNode, TRUE);
    $unpublishedOwn = $this->createComment($stewardedNode, FALSE);
    $otherComment = $this->createComment($otherNode, FALSE);

    $build = $this->callAsUser($steward, 'myComments');

    $this->assertArrayHasKey('#rows', $build, 'Steward sees a comments table, not the empty-state markup.');
    $subjects = array_map(fn($row) => $row[0]['data'], $build['#rows']);
    $this->assertContains($publishedOwn->getSubject(), $subjects);
    $this->assertContains($unpublishedOwn->getSubject(), $subjects);
    $this->assertNotContains($otherComment->getSubject(), $subjects);
  }

  /**
   * A user who stewards no protocols sees the empty state, not an error.
   */
  public function testMyCommentsEmptyForNonSteward() {
    $nonSteward = User::create(['name' => $this->randomString()]);
    $nonSteward->save();

    $build = $this->callAsUser($nonSteward, 'myComments');
    $this->assertArrayNotHasKey('#rows', $build);
    $this->assertArrayHasKey('#markup', $build);
  }

  /**
   * unpublishComment() unpublishes and persists the comment.
   */
  public function testUnpublishCommentPersists() {
    $protocol = $this->createProtocol();
    $node = $this->createContentForProtocol($protocol);
    $comment = $this->createComment($node, TRUE);

    $this->assertTrue($comment->isPublished());

    $this->callAsUser($this->owner, 'unpublishComment', $comment);

    $reloaded = \Drupal::entityTypeManager()->getStorage('comment')->load($comment->id());
    $this->assertFalse($reloaded->isPublished());
  }

  /**
   * The comment_unpublish route's _entity_access: comment.update requirement
   * denies a steward for a comment on content outside their protocol - the
   * same view-access gate exercised in MukurtuCommentAccessControlHandlerTest,
   * confirmed here at the route-access level.
   */
  public function testUnpublishRouteAccessDeniedForNonStewardedContent() {
    $protocol = $this->createProtocol();
    $otherProtocol = $this->createProtocol();
    $node = $this->createContentForProtocol($protocol);
    $comment = $this->createComment($node, TRUE);

    $steward = User::create(['name' => $this->randomString()]);
    $steward->save();
    $otherProtocol->addMember($steward, ['protocol_steward']);

    $this->assertFalse($comment->access('update', $steward));
  }

  /**
   * Regression guard for the {group} -> {protocol} route parameter rename:
   * ProtocolCommentSettingsController::access() must accept the protocol
   * entity as its second argument (matching the renamed route parameter)
   * and correctly gate the per-protocol unapproved-comments page (and, by
   * extension, its new "Unapproved comments" local task on the protocol
   * canonical page) to stewards of that specific protocol.
   */
  public function testPerProtocolAccessCallbackUsesRenamedParameter() {
    $protocol = $this->createProtocol();
    $otherProtocol = $this->createProtocol();

    $steward = User::create(['name' => $this->randomString()]);
    $steward->save();
    $protocol->addMember($steward, ['protocol_steward']);

    $nonSteward = User::create(['name' => $this->randomString()]);
    $nonSteward->save();

    $controller = ProtocolCommentSettingsController::create(\Drupal::getContainer());

    $this->assertTrue($controller->access($steward, $protocol)->isAllowed());
    $this->assertFalse($controller->access($nonSteward, $protocol)->isAllowed());
    $this->assertFalse($controller->access($steward, $otherProtocol)->isAllowed());
  }

}
