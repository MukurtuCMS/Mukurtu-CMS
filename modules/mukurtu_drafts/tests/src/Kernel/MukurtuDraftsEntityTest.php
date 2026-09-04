<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\drafts_entity_test\Entity\TestDraftEntity;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests access control for draft-state content.
 */
#[Group('mukurtu_drafts')]
class MukurtuDraftsEntityTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'drafts_entity_test',
    'entity_test',
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_workflows',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // mukurtu_drafts itself is not enabled here: its declared dependency on
    // mukurtu_core pulls in a large contrib module chain (search_api,
    // layout_builder, paragraphs, etc.) that isn't needed to exercise this
    // one procedural hook. Load the .module file directly so
    // mukurtu_drafts_entity_access() is defined.
    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_drafts') . '/mukurtu_drafts.module';

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('drafts_entity_test');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    $this->installConfig(['og']);

    node_access_rebuild();
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'thing');

    // The first user saved in a fresh kernel DB becomes uid 1, which bypasses
    // every permission check via SuperUserAccessPolicy. Burn it here so the
    // users created in each test actually exercise the permission checks
    // below instead of silently passing as the superuser.
    User::create(['name' => 'burner'])->save();
  }

  /**
   * Creates a saved user with no roles or permissions.
   */
  protected function createPlainUser(): User {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    return $user;
  }

  /**
   * Creates a saved user with a role granting the given permission.
   */
  protected function createUserWithPermission(string $permission): User {
    $role = Role::create([
      'id' => $this->randomMachineName(8),
      'label' => $permission,
    ]);
    $role->grantPermission($permission);
    $role->save();

    $user = $this->createPlainUser();
    $user->addRole($role->id());
    $user->save();
    return $user;
  }

  /**
   * Creates a saved page node in the given moderation state, owned by $uid.
   */
  protected function createModeratedNode(string $moderation_state, int $uid): Node {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomString(),
      'uid' => $uid,
      'moderation_state' => $moderation_state,
    ]);
    $node->save();
    return $node;
  }

  /**
   * The owner of a draft can view it.
   */
  public function testOwnerCanViewOwnDraft(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'view', $owner);
    $this->assertTrue($result->isAllowed(), 'Owner can view their own draft.');
  }

  /**
   * A non-owner with no special permissions cannot view another user's draft.
   */
  public function testNonOwnerIsForbiddenFromViewingDraft(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());
    $other = $this->createPlainUser();

    $result = mukurtu_drafts_entity_access($node, 'view', $other);
    $this->assertTrue($result->isForbidden(), "Non-owner is forbidden from viewing another user's draft.");
  }

  /**
   * The bypass node access permission defers to core access.
   */
  public function testBypassNodeAccessIsNeutral(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());
    $admin = $this->createUserWithPermission('bypass node access');

    $result = mukurtu_drafts_entity_access($node, 'view', $admin);
    $this->assertTrue($result->isNeutral(), 'bypass node access should be neutral, not an explicit allow.');
  }

  /**
   * The administer nodes permission defers to core access.
   */
  public function testAdministerNodesIsNeutral(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());
    $admin = $this->createUserWithPermission('administer nodes');

    $result = mukurtu_drafts_entity_access($node, 'view', $admin);
    $this->assertTrue($result->isNeutral(), 'administer nodes should be neutral, not an explicit allow.');
  }

  /**
   * The view any unpublished content permission defers to core access.
   */
  public function testViewAnyUnpublishedContentIsNeutral(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());
    $reviewer = $this->createUserWithPermission('view any unpublished content');

    $result = mukurtu_drafts_entity_access($node, 'view', $reviewer);
    $this->assertTrue($result->isNeutral(), 'view any unpublished content should be neutral, not an explicit allow.');
  }

  /**
   * Non-draft content is untouched by this hook, regardless of who's asking.
   */
  public function testNonDraftNodeIsNeutralRegardlessOfUser(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('published', (int) $owner->id());
    $other = $this->createPlainUser();

    $result = mukurtu_drafts_entity_access($node, 'view', $other);
    $this->assertTrue($result->isNeutral(), 'Non-draft content should be neutral regardless of user.');
  }

  /**
   * Entities without a moderation_state field are untouched by this hook.
   */
  public function testEntityWithoutModerationStateIsNeutral(): void {
    $entity = TestDraftEntity::create([
      'type' => 'drafts_entity_test',
      'name' => $this->randomString(),
    ]);
    $user = $this->createPlainUser();

    $result = mukurtu_drafts_entity_access($entity, 'view', $user);
    $this->assertTrue($result->isNeutral(), 'Entities without a moderation_state field should be neutral.');
  }

  /**
   * Non-view operations on a non-owner's draft are neutral, not forbidden.
   *
   * Edit/delete are governed by workflow transition permissions elsewhere.
   */
  public function testNonOwnerUpdateAndDeleteAreNeutralNotForbidden(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('draft', (int) $owner->id());
    $other = $this->createPlainUser();

    $update_result = mukurtu_drafts_entity_access($node, 'update', $other);
    $delete_result = mukurtu_drafts_entity_access($node, 'delete', $other);

    $this->assertTrue($update_result->isNeutral(), 'Update on a draft should be neutral, not forbidden.');
    $this->assertTrue($delete_result->isNeutral(), 'Delete on a draft should be neutral, not forbidden.');
  }

  /**
   * An owner can no longer update their own archived structural content --
   * closes the loophole where "edit own X content" would otherwise let an
   * author silently undo a manager's archive decision.
   */
  public function testOwnerCannotUpdateOwnArchivedStructuralContent(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('archived', (int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'update', $owner);
    $this->assertTrue($result->isForbidden(), 'Owner cannot update their own archived content.');
  }

  /**
   * A user holding "edit any page content" (e.g. Mukurtu Manager) is
   * unaffected even if they happen to also own the archived node.
   */
  public function testOwnerWithEditAnyPermissionIsUnaffectedOnArchived(): void {
    $owner = $this->createUserWithPermission('edit any page content');
    $node = $this->createModeratedNode('archived', (int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'update', $owner);
    $this->assertTrue($result->isNeutral(), 'A privileged edit-any user is unaffected, even as the owner.');
  }

  /**
   * A non-owner's update access to archived content is untouched (neutral),
   * consistent with the draft-state behavior above.
   */
  public function testNonOwnerUpdateOnArchivedIsNeutral(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('archived', (int) $owner->id());
    $other = $this->createPlainUser();

    $result = mukurtu_drafts_entity_access($node, 'update', $other);
    $this->assertTrue($result->isNeutral(), 'Non-owner update on archived content is neutral, unaffected by this hook.');
  }

  /**
   * Delete on an owned archived node is untouched -- this hook only
   * narrows 'update' and 'view'.
   */
  public function testDeleteOnOwnedArchivedIsUntouched(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('archived', (int) $owner->id());

    $delete_result = mukurtu_drafts_entity_access($node, 'delete', $owner);
    $this->assertTrue($delete_result->isNeutral(), 'Delete on archived content is untouched.');
  }

  /**
   * An owner can no longer view their own archived structural content --
   * archived means unpublished, and only genuinely privileged accounts
   * should still have access, the same way non-owners are already
   * blocked from viewing another user's draft.
   */
  public function testOwnerCannotViewOwnArchivedStructuralContent(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('archived', (int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'view', $owner);
    $this->assertTrue($result->isForbidden(), 'Owner cannot view their own archived content.');
  }

  /**
   * A user holding "view any unpublished content" (e.g. Mukurtu Manager)
   * is unaffected even if they happen to also own the archived node.
   */
  public function testOwnerWithViewAnyUnpublishedPermissionIsUnaffectedOnArchived(): void {
    $owner = $this->createUserWithPermission('view any unpublished content');
    $node = $this->createModeratedNode('archived', (int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'view', $owner);
    $this->assertTrue($result->isNeutral(), 'A privileged view-any-unpublished user is unaffected, even as the owner.');
  }

  /**
   * A non-owner's view access to archived content is untouched (neutral)
   * by this hook -- core's own unpublished-content rules already deny a
   * non-owner, non-privileged viewer without any help from here.
   */
  public function testNonOwnerViewOnArchivedIsNeutral(): void {
    $owner = $this->createPlainUser();
    $node = $this->createModeratedNode('archived', (int) $owner->id());
    $other = $this->createPlainUser();

    $result = mukurtu_drafts_entity_access($node, 'view', $other);
    $this->assertTrue($result->isNeutral(), 'Non-owner view on archived content is neutral, unaffected by this hook.');
  }

  /**
   * An owner of archived content WITH a protocol assigned is entirely
   * unaffected by this hook (neutral) -- that case is fully governed by
   * MukurtuProtocolNodeAccessControlHandler/OG, and this hook must defer
   * to it rather than risk vetoing a real Protocol Steward grant.
   */
  public function testOwnerOfArchivedProtocolGatedContentIsUnaffected(): void {
    $owner = $this->createPlainUser();
    $this->container->get('current_user')->setAccount($owner);

    $community = Community::create(['name' => 'Community 1']);
    $community->save();
    $community->addMember($owner);

    $protocol = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();

    $contributorRole = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => ['apply protocol', 'create thing node'],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();
    $protocol->addMember($owner, ['contributor']);

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $owner->id(),
      'moderation_state' => 'archived',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$protocol]);
    $node->save();

    $result = mukurtu_drafts_entity_access($node, 'update', $owner);
    $this->assertTrue($result->isNeutral(), 'Protocol-gated archived content is deferred entirely to the protocol access handler.');
  }

}
