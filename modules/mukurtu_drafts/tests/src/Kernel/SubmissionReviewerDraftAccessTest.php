<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * mukurtu_drafts_entity_access() lifts the draft-visibility veto for a
 * submission reviewer looking at a node that has a submission record, so
 * MukurtuProtocolNodeAccessControlHandler can then admit them via the
 * per-bundle grant.
 *
 * @see mukurtu_drafts_entity_access()
 */
#[Group('mukurtu_drafts')]
class SubmissionReviewerDraftAccessTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
    'mukurtu_submissions',
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

    // As in MukurtuDraftsEntityTest: mukurtu_drafts is not enabled (its
    // mukurtu_core dependency chain is heavy), the one procedural hook is
    // loaded directly. mukurtu_submissions IS enabled - bare, no config -
    // so moduleExists() is TRUE and its .module helper is available.
    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_drafts') . '/mukurtu_drafts.module';

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('mukurtu_submission');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');

    // Burn uid 1 (SuperUserAccessPolicy bypasses every check).
    User::create(['name' => 'burner'])->save();
  }

  protected function createReviewer(): User {
    $role = Role::create(['id' => 'submission_reviewer_test', 'label' => 'Reviewer']);
    $role->grantPermission('review mukurtu submissions');
    $role->save();
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->addRole($role->id());
    $user->save();
    return $user;
  }

  protected function createDraftNode(int $uid): Node {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomString(),
      'uid' => $uid,
      'moderation_state' => 'draft',
    ]);
    $node->save();
    return $node;
  }

  protected function createSubmissionFor(Node $node): void {
    \Drupal::entityTypeManager()->getStorage('mukurtu_submission')->create([
      'target_entity_type' => 'node',
      'target_id' => (int) $node->id(),
      'submitter_name' => 'Visitor',
      'submitter_email' => 'visitor@example.com',
    ])->save();
  }

  /**
   * Reviewer + a submission record: the veto is lifted (neutral, not
   * forbidden - the protocol handler makes the final call downstream).
   */
  public function testReviewerWithSubmissionRecordIsNotVetoed(): void {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
    $node = $this->createDraftNode((int) $owner->id());
    $this->createSubmissionFor($node);

    $result = mukurtu_drafts_entity_access($node, 'view', $this->createReviewer());
    $this->assertTrue($result->isNeutral());
    $this->assertFalse($result->isForbidden());
  }

  /**
   * Reviewer but no submission record for this node: still vetoed. The
   * bypass is scoped to actual submissions, not every draft.
   */
  public function testReviewerWithoutSubmissionRecordIsStillForbidden(): void {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
    $node = $this->createDraftNode((int) $owner->id());

    $result = mukurtu_drafts_entity_access($node, 'view', $this->createReviewer());
    $this->assertTrue($result->isForbidden());
  }

  /**
   * A submission record but the user cannot review submissions: still
   * vetoed.
   */
  public function testNonReviewerWithSubmissionRecordIsStillForbidden(): void {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
    $node = $this->createDraftNode((int) $owner->id());
    $this->createSubmissionFor($node);

    $plain = User::create(['name' => $this->randomMachineName()]);
    $plain->save();

    $result = mukurtu_drafts_entity_access($node, 'view', $plain);
    $this->assertTrue($result->isForbidden());
  }

}
