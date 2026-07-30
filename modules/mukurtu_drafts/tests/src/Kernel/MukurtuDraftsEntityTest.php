<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\drafts_entity_test\Entity\TestDraftEntity;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
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
    'field',
    'file',
    'image',
    'media',
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('drafts_entity_test');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');

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

}
