<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Entity\Submission;
use Drupal\mukurtu_submissions\Entity\SubmissionInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests that mukurtu_submissions_node_update() keeps a submission's
 * review_status in sync with its node's moderation state.
 *
 * Nothing else in the codebase ever called Submission::setReviewStatus() -
 * a submission defaulted to "pending" at creation and stayed that way
 * forever, so it never left the Pending Submissions view even after a
 * reviewer published or archived it (see mukurtu_submissions_update_40010()
 * for the retrofit of already-affected sites).
 *
 * @group mukurtu_submissions
 */
class SubmissionReviewStatusSyncTest extends MukurtuSubmissionsKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('mukurtu_submission');
    $this->installSchema('node', ['node_access']);

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', static::TEST_BUNDLE);
  }

  protected function createDraftNode(): Node {
    $node = Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => 'Test content',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    return $node;
  }

  protected function createSubmissionForNode(Node $node): SubmissionInterface {
    $submission = Submission::create([
      'target_entity_type' => 'node',
      'target_id' => $node->id(),
      'submitter_name' => 'Test Submitter',
      'submitter_email' => 'test@example.com',
    ]);
    $submission->save();
    return $submission;
  }

  public function testPublishingMarksSubmissionApproved(): void {
    $node = $this->createDraftNode();
    $submission = $this->createSubmissionForNode($node);
    $this->assertEquals(SubmissionInterface::STATUS_PENDING, $submission->getReviewStatus());

    $node->set('moderation_state', 'published');
    $node->save();

    $submission = Submission::load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_APPROVED, $submission->getReviewStatus());
  }

  public function testArchivingMarksSubmissionRejected(): void {
    $node = $this->createDraftNode();
    $submission = $this->createSubmissionForNode($node);

    $node->set('moderation_state', 'archived');
    $node->save();

    $submission = Submission::load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_REJECTED, $submission->getReviewStatus());
  }

  public function testReturningToDraftKeepsSubmissionPending(): void {
    $node = $this->createDraftNode();
    $submission = $this->createSubmissionForNode($node);

    $node->set('moderation_state', 'published');
    $node->save();
    $node->set('moderation_state', 'draft');
    $node->save();

    $submission = Submission::load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_PENDING, $submission->getReviewStatus());
  }

  public function testNodeWithoutSubmissionIsNoOp(): void {
    $node = $this->createDraftNode();
    $node->set('moderation_state', 'published');
    $node->save();
    $this->assertTrue(TRUE);
  }

  public function testSavingWithoutModerationStateChangeStaysApproved(): void {
    $node = $this->createDraftNode();
    $submission = $this->createSubmissionForNode($node);

    $node->set('moderation_state', 'published');
    $node->save();

    // Re-saving the node without changing moderation_state should leave
    // the already-synced status alone.
    $node->setTitle('Test content (edited)');
    $node->save();

    $submission = Submission::load($submission->id());
    $this->assertEquals(SubmissionInterface::STATUS_APPROVED, $submission->getReviewStatus());
  }

}
