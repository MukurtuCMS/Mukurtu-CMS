<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_submissions\Entity\SubmissionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests mukurtu_submissions_node_update(), which keeps a mukurtu_submission
 * entity's review_status in sync with its node's moderation state so the
 * Pending Submissions queue (filtered on review_status = pending) actually
 * empties once a reviewer publishes the submission.
 *
 * @group mukurtu_submissions
 */
class SubmissionReviewStatusSyncTest extends EntityKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'node',
    'content_moderation',
    'workflows',
    'text',
    'mukurtu_submissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('mukurtu_submission');

    NodeType::create(['type' => 'sync_test', 'name' => 'Sync test'])->save();

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'sync_test');
  }

  /**
   * Creates a draft node plus a pending mukurtu_submission pointing at it.
   */
  protected function createPendingSubmission(): array {
    $node = Node::create([
      'type' => 'sync_test',
      'title' => $this->randomString(),
      'uid' => $this->createUser()->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->create([
      'target_entity_type' => 'node',
      'target_id' => $node->id(),
      'submitter_name' => $this->randomString(),
      'submitter_email' => 'submitter@example.com',
    ]);
    $submission->save();

    return [$node, $submission];
  }

  public function testReviewStatusApprovedWhenNodeIsPublished(): void {
    [$node, $submission] = $this->createPendingSubmission();

    $node->set('moderation_state', 'published');
    $node->save();

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_APPROVED, $submission->getReviewStatus());
  }

  public function testReviewStatusStaysPendingWithoutModerationStateChange(): void {
    [$node, $submission] = $this->createPendingSubmission();

    // An unrelated edit that doesn't touch moderation_state (still draft)
    // must not affect review_status.
    $node->setTitle('Updated title');
    $node->save();

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_PENDING, $submission->getReviewStatus());
  }

  public function testAlreadyReviewedSubmissionIsNotOverwritten(): void {
    [$node, $submission] = $this->createPendingSubmission();
    $submission->setReviewStatus(SubmissionInterface::STATUS_REJECTED)->save();

    $node->set('moderation_state', 'published');
    $node->save();

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_REJECTED, $submission->getReviewStatus());
  }

  public function testNodeWithoutSubmissionIsUnaffected(): void {
    $node = Node::create([
      'type' => 'sync_test',
      'title' => $this->randomString(),
      'uid' => $this->createUser()->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // No mukurtu_submission entity exists for this node - publishing it
    // must not error.
    $node->set('moderation_state', 'published');
    $node->save();

    $this->assertTrue((bool) $node->isPublished());
  }

}
